<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'agents';

    /**
     * Keep the legacy `updateDate` column for older admin screens, while
     * tracking the authoritative last-modified time in `updated_at`.
     */
    public $timestamps = true;

    const CREATED_AT = null;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'address',
        'email',
        'phone',
        'validDate',
        'is_active',
        'displayorder',
        'external_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'updated_at' => 'datetime',
        'external_synced_at' => 'datetime',
    ];

    protected $appends = ['is_active', 'is_current'];

    protected static function booted(): void
    {
        // New records get the next sequence number automatically, so the
        // order is always controlled by this system and never by sync input.
        static::creating(function (Agent $agent) {
            if ($agent->displayorder === null || $agent->displayorder === '') {
                $agent->displayorder = static::nextDisplayOrder();
            }
        });

        // Ensure is_active is explicitly 1 or 0 in DB if not manually set.
        static::saving(function (Agent $agent) {
            if (! $agent->hasManualStatus()) {
                $agent->attributes['is_active'] = $agent->is_active;
            }
        });

        // "Latest record wins": when a record is written, any older record
        // representing the same person (name/phone/email) is kept as history
        // but marked inactive. A manual `is_active = false` is never cascaded.
        static::created(function (Agent $agent) {
            $agent->deactivateOlderRecords();
        });

        static::updated(function (Agent $agent) {
            if ($agent->wasChanged(['name', 'email', 'phone', 'validDate', 'is_active', 'updated_at'])) {
                $agent->deactivateOlderRecords();
            }
        });
    }

    /**
     * Determine whether the agent is currently active.
     * If is_active has been explicitly set, it wins. Otherwise we fall back to
     * the existing validDate-based logic.
     */
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
            $parsedDate = null;

            // Handle DD-MM-YYYY or DD/MM/YYYY
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

    /**
     * True when an admin explicitly set is_active (true or false).
     */
    public function hasManualStatus(): bool
    {
        return array_key_exists('is_active', $this->attributes)
            && $this->attributes['is_active'] !== null;
    }

    /**
     * True when the record was explicitly disabled, which must not cascade to
     * other records for the same person.
     */
    public function isManuallyInactive(): bool
    {
        return $this->hasManualStatus() && ! (bool) $this->attributes['is_active'];
    }

    /**
     * Next sequence number: last existing displayorder + 1.
     */
    public static function nextDisplayOrder(): int
    {
        $last = static::query()->max('displayorder');

        return $last === null ? 1 : ((int) $last + 1);
    }

    /**
     * Last time this record was written (authoritative updated_at).
     */
    public function getLastModifiedAtAttribute()
    {
        return $this->updated_at;
    }

    /**
     * Whether this is the newest stored record for the same person.
     */
    public function getIsCurrentAttribute(): bool
    {
        if (! $this->exists) {
            return true;
        }

        $latest = static::query()
            ->forIdentity($this->identity())
            ->whereKeyNot($this->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return true;
        }

        return $this->compareRecencyTo($latest) >= 0;
    }

    /**
     * Mark older records for the same person as inactive.
     * Only the newest record keeps its status; older rows stay as history.
     */
    public function deactivateOlderRecords(): int
    {
        if (! $this->exists || $this->isManuallyInactive()) {
            return 0;
        }

        $olderIds = static::query()
            ->forIdentity($this->identity())
            ->whereKeyNot($this->getKey())
            ->get()
            ->filter(fn (Agent $candidate) => $this->compareRecencyTo($candidate) > 0)
            ->pluck('id');

        if ($olderIds->isEmpty()) {
            return 0;
        }

        // Direct update so the old rows' updated_at is not refreshed; they
        // must remain older than the record that superseded them.
        return static::query()
            ->whereKey($olderIds->all())
            ->update(['is_active' => false]);
    }

    /**
     * Scope to records sharing the supplied identity values (case-insensitive).
     */
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

    /**
     * Identity values used to decide whether two rows are the same person.
     */
    public function identity(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    /**
     * Compare recency: positive when this record is newer than $other.
     */
    protected function compareRecencyTo(Agent $other): int
    {
        $mine = $this->lastModifiedAt;
        $theirs = $other->lastModifiedAt;

        $mineTime = $mine ? Carbon::parse($mine)->getTimestamp() : 0;
        $theirsTime = $theirs ? Carbon::parse($theirs)->getTimestamp() : 0;

        if ($mineTime !== $theirsTime) {
            return $mineTime <=> $theirsTime;
        }

        return (int) $this->getKey() <=> (int) $other->getKey();
    }
}
