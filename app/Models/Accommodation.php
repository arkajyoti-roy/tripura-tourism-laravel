<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Accommodation extends Model
{
    protected $table = 'accommodation';
    protected $primaryKey = 'aid';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'image',
        'link',
        'type',
        'description',
        'contact',
        'image2',
        'image3',
        'displayorder',
        'email',
        'website',
        'valid_upto',
        'address',
        'name_of_lessee',
        'is_active',
        'external_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'external_synced_at' => 'datetime',
    ];

    protected $appends = ['is_active', 'image_url'];

    public const TYPE_GOVT = 1;
    public const TYPE_PRIVATE = 2;
    public const TYPE_LEASEOUT = 3;
    public const TYPE_HOMESTAY = 4;

    protected static function booted(): void
    {
        // Auto display order if empty
        static::creating(function (Accommodation $acc) {
            if ($acc->displayorder === null || $acc->displayorder === '') {
                $last = static::query()->where('type', $acc->type)->max('displayorder');
                $acc->displayorder = $last === null ? 1 : ((int) $last + 1);
            }
        });

        // Ensure is_active is explicitly 1 or 0 in DB if not manually set
        static::saving(function (Accommodation $acc) {
            if (! $acc->hasManualStatus()) {
                $acc->attributes['is_active'] = $acc->is_active;
            }
        });

        // "Latest record wins" for homestays & private hotels
        static::created(function (Accommodation $acc) {
            if (in_array((int) $acc->type, [self::TYPE_HOMESTAY, self::TYPE_PRIVATE], true)) {
                $acc->deactivateOlderDuplicateRecords();
            }
        });

        static::updated(function (Accommodation $acc) {
            if (in_array((int) $acc->type, [self::TYPE_HOMESTAY, self::TYPE_PRIVATE], true) && $acc->wasChanged(['name', 'email', 'contact', 'valid_upto', 'is_active', 'updated_at'])) {
                $acc->deactivateOlderDuplicateRecords();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = date('Y-m-d');
        $nowTimestamp = time();
        $dateExpr = static::validDateExpression();

        return $query->where(function (Builder $q) use ($today, $nowTimestamp, $dateExpr) {
            // Manual true always wins
            $q->where('is_active', true)
                ->orWhere(function (Builder $sub) use ($today, $nowTimestamp, $dateExpr) {
                    // When not manually set (null), check license validity
                    $sub->whereNull('is_active')
                        ->where(function (Builder $date) use ($today, $nowTimestamp, $dateExpr) {
                            $date->whereNull('valid_upto')
                                ->orWhere('valid_upto', '')
                                ->orWhere(function (Builder $d) use ($nowTimestamp) {
                                    // Numeric unix timestamp check
                                    $d->whereRaw("valid_upto REGEXP '^[0-9]{9,11}$'")
                                      ->whereRaw("CAST(valid_upto AS UNSIGNED) >= ?", [$nowTimestamp]);
                                })
                                ->orWhere(function (Builder $d) use ($today, $dateExpr) {
                                    // Date string check (YYYY-MM-DD or DD-MM-YYYY)
                                    $d->whereRaw("NOT (valid_upto REGEXP '^[0-9]{9,11}$')")
                                      ->whereRaw("{$dateExpr} >= ?", [$today]);
                                });
                        });
                });
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        $today = date('Y-m-d');
        $nowTimestamp = time();
        $dateExpr = static::validDateExpression();

        return $query->where(function (Builder $q) use ($today, $nowTimestamp, $dateExpr) {
            // Manual false wins
            $q->where('is_active', false)
                ->orWhere(function (Builder $sub) use ($today, $nowTimestamp, $dateExpr) {
                    $sub->whereNull('is_active')
                        ->whereNotNull('valid_upto')
                        ->where('valid_upto', '!=', '')
                        ->where(function (Builder $date) use ($today, $nowTimestamp, $dateExpr) {
                            $date->where(function (Builder $d) use ($nowTimestamp) {
                                $d->whereRaw("valid_upto REGEXP '^[0-9]{9,11}$'")
                                  ->whereRaw("CAST(valid_upto AS UNSIGNED) < ?", [$nowTimestamp]);
                            })->orWhere(function (Builder $d) use ($today, $dateExpr) {
                                $d->whereRaw("NOT (valid_upto REGEXP '^[0-9]{9,11}$')")
                                  ->whereRaw("{$dateExpr} < ?", [$today]);
                            });
                        });
                });
        });
    }

    /**
     * SQL expression normalising date strings (YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY).
     */
    public static function validDateExpression(): string
    {
        return "(CASE WHEN valid_upto REGEXP '^[0-9]{2}[-/][0-9]{2}[-/][0-9]{4}' ".
            "THEN STR_TO_DATE(SUBSTRING(REPLACE(valid_upto, '/', '-'), 1, 10), '%d-%m-%Y') ".
            "ELSE STR_TO_DATE(SUBSTRING(REPLACE(valid_upto, '/', '-'), 1, 10), '%Y-%m-%d') END)";
    }

    public function scopeHomestays(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_HOMESTAY);
    }

    public function scopePrivateHotels(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PRIVATE);
    }

    /**
     * Compute active status: manual status wins, else date fallback.
     */
    public function getIsActiveAttribute(): bool
    {
        if ($this->hasManualStatus()) {
            return (bool) $this->attributes['is_active'];
        }

        if (empty($this->valid_upto)) {
            return true;
        }

        try {
            $rawDate = trim((string) $this->valid_upto);

            // Handle unix timestamp stored in some legacy records
            if (is_numeric($rawDate) && strlen($rawDate) >= 9) {
                return Carbon::createFromTimestamp((int) $rawDate)->endOfDay()->greaterThanOrEqualTo(Carbon::today());
            }

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
        if (! $this->image) {
            return null;
        }

        $path = ((int) $this->type === self::TYPE_HOMESTAY)
            ? 'images/accommodation/homestay/'
            : 'images/accommodation/';

        return asset($path . $this->image);
    }

    /**
     * Deactivate older duplicate records representing the same homestay or private hotel.
     */
    public function deactivateOlderDuplicateRecords(): int
    {
        if (! $this->exists || $this->isManuallyInactive()) {
            return 0;
        }

        $olderIds = static::query()
            ->where('type', $this->type)
            ->forIdentity($this->identity())
            ->whereKeyNot($this->getKey())
            ->get()
            ->filter(fn (Accommodation $cand) => $this->compareRecencyTo($cand) > 0)
            ->pluck('aid');

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

        foreach (['email', 'contact', 'name'] as $field) {
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
            'contact' => $this->contact,
            'email' => $this->email,
        ];
    }

    protected function compareRecencyTo(Accommodation $other): int
    {
        $mineTime = $this->updated_at ? Carbon::parse($this->updated_at)->getTimestamp() : 0;
        $theirsTime = $other->updated_at ? Carbon::parse($other->updated_at)->getTimestamp() : 0;

        if ($mineTime !== $theirsTime) {
            return $mineTime <=> $theirsTime;
        }

        return (int) $this->getKey() <=> (int) $other->getKey();
    }
}
