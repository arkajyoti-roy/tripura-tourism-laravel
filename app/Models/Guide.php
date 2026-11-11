<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    protected $table = 'guides';

    public $timestamps = true;

    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'validDate',
        'image',
        'language',
        'spot',
        'displayorder',
        'is_active',
        'external_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'updated_at' => 'datetime',
        'external_synced_at' => 'datetime',
    ];

    protected $appends = ['is_active', 'image_url'];

    protected static function booted(): void
    {
        static::creating(function (Guide $guide) {
            if ($guide->displayorder === null || $guide->displayorder === '') {
                $last = static::query()->max('displayorder');
                $guide->displayorder = $last === null ? 1 : ((int) $last + 1);
            }
        });

        static::saving(function (Guide $guide) {
            if (! $guide->hasManualStatus()) {
                $guide->attributes['is_active'] = $guide->is_active;
            }
        });

        static::created(function (Guide $guide) {
            $guide->deactivateOlderRecords();
        });

        static::updated(function (Guide $guide) {
            if ($guide->wasChanged(['name', 'email', 'phone', 'validDate', 'is_active', 'updated_at'])) {
                $guide->deactivateOlderRecords();
            }
        });
    }

    public function getIsActiveAttribute(): bool
    {
        if ($this->hasManualStatus()) {
            return (bool) $this->attributes['is_active'];
        }

        if (empty($this->validDate)) {
            return true;
        }

        try {
            $rawDate = trim((string) $this->validDate);
            if (preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{4}$/', $rawDate)) {
                $separator = str_contains($rawDate, '/') ? '/' : '-';
                $parts = explode($separator, $rawDate);
                $parsedDate = Carbon::createFromDate((int) $parts[2], (int) $parts[1], (int) $parts[0])->startOfDay();
            } else {
                $parsedDate = Carbon::parse($rawDate)->startOfDay();
            }

            return $parsedDate->greaterThanOrEqualTo(Carbon::today());
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function hasManualStatus(): bool
    {
        return array_key_exists('is_active', $this->attributes)
            && $this->attributes['is_active'] !== null;
    }

    public function isManuallyInactive(): bool
    {
        return $this->hasManualStatus() && ! (bool) $this->attributes['is_active'];
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('images/guides/' . $this->image) : null;
    }

    public function deactivateOlderRecords(): int
    {
        if (! $this->exists || $this->isManuallyInactive()) {
            return 0;
        }

        $olderIds = static::query()
            ->forIdentity($this->identity())
            ->whereKeyNot($this->getKey())
            ->get()
            ->filter(fn (Guide $cand) => $this->compareRecencyTo($cand) > 0)
            ->pluck('id');

        if ($olderIds->isEmpty()) {
            return 0;
        }

        return static::query()
            ->whereKey($olderIds->all())
            ->update(['is_active' => false]);
    }

    public function scopeForIdentity(Builder $query, array $identity): Builder
    {
        $matches = [];

        foreach (['email', 'phone', 'name'] as $field) {
            $value = trim((string) ($identity[$field] ?? ''));
            if ($value !== '') {
                $matches[] = [$field, mb_strtolower($value)];
            }
        }

        if (empty($matches)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($matches) {
            foreach ($matches as [$field, $value]) {
                $inner->orWhereRaw("LOWER({$field}) = ?", [$value]);
            }
        });
    }

    public function identity(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    protected function compareRecencyTo(Guide $other): int
    {
        $mineTime = $this->updated_at ? Carbon::parse($this->updated_at)->getTimestamp() : 0;
        $theirsTime = $other->updated_at ? Carbon::parse($other->updated_at)->getTimestamp() : 0;

        if ($mineTime !== $theirsTime) {
            return $mineTime <=> $theirsTime;
        }

        return (int) $this->getKey() <=> (int) $other->getKey();
    }
}

