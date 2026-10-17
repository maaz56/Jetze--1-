<?php

namespace App\Models;

use App\Jobs\RefreshPopularRoutePriceJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class PopularRoute extends Model
{
    use HasFactory;

    protected $table = 'popular_routes';

    protected $fillable = [
        'slug',
        'from_airport',
        'to_airport',
        'type',
        'is_active',
        'is_featured',
        'sort_order',
        'image',
        'airline_id',
        'journey_type',
        'travel_class',
        'departure_plus_days',
        'stay_duration_days',
        'price_type',
        'dynamic_refresh_hours',
        'static_price',
        'dynamic_price',
        'dynamic_price_currency',
        'dynamic_price_meta',
        'price_checked_at',
        'price_updated_at',
        'next_price_refresh_at',
        'price_status',
        'price_failure_count',
        'price_last_error',
        'destination_name_en',
        'destination_name_ar',
        'blogs',
        'faqs',
    ];

    protected $appends = ['from_city', 'to_city', 'effective_price', 'computed_slug'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'dynamic_price' => 'decimal:2',
        'dynamic_price_meta' => 'array',
        'price_checked_at' => 'datetime',
        'price_updated_at' => 'datetime',
        'next_price_refresh_at' => 'datetime',
        'price_failure_count' => 'integer',
        'blogs' => 'array',
        'faqs' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PopularRoute $route) {
            if (empty($route->slug)) {
                $route->slug = $route->generateUniqueSlug();
            }
            if ($route->price_type === 'dynamic') {
                $route->next_price_refresh_at = now();
                $route->price_status = 'pending';
            }
        });

        static::created(function (PopularRoute $route) {
            if ($route->price_type === 'dynamic') {
                RefreshPopularRoutePriceJob::dispatch($route->id)->afterCommit();
            }
        });

        static::updated(function (PopularRoute $route) {
            if ($route->wasChanged($route->dynamicRefreshTriggerColumns())) {
                if ($route->price_type === 'dynamic') {
                    $route->forceFill([
                        'next_price_refresh_at' => now(),
                        'price_status' => 'pending',
                    ])->saveQuietly();

                    RefreshPopularRoutePriceJob::dispatch($route->id)->afterCommit();
                }
            }
        });

        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forever('popular_routes:version', time());
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forever('popular_routes:version', time());
        });
    }

    public function generateUniqueSlug(): string
    {
        $base = Str::slug(($this->destination_name_en ?: 'flights') . '-' . ($this->from_airport ?: 'origin') . '-' . ($this->to_airport ?: 'dest'));
        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function getComputedSlugAttribute(): string
    {
        return $this->slug ?: Str::slug(($this->destination_name_en ?: 'flights') . '-' . $this->from_airport . '-' . $this->to_airport);
    }

    public function getEffectivePriceAttribute(): ?array
    {
        $maxAgeHours = (int) config('popular_routes.max_price_age_hours', 72);
        $baseCurrency = config('popular_routes.base_currency', 'AED');

        if ($this->price_type === 'static') {
            if ($this->static_price === null || (float) $this->static_price <= 0) {
                return null;
            }

            return [
                'amount' => (float) $this->static_price,
                'currency' => $this->dynamic_price_currency ?: $baseCurrency,
                'source' => 'static',
                'updated_at' => $this->updated_at?->toISOString(),
                'is_stale' => false,
            ];
        }

        // Dynamic route price resolution
        $amount = $this->dynamic_price !== null ? (float) $this->dynamic_price : null;

        // Fallback to static_price if dynamic_price column not yet migrated
        if ($amount === null && $this->static_price !== null && (float) $this->static_price > 0) {
            $amount = (float) $this->static_price;
        }

        if ($amount === null || $amount <= 0) {
            return null;
        }

        $lastUpdated = $this->price_updated_at ?: $this->updated_at;
        $isExpired = $lastUpdated && $lastUpdated->lt(now()->subHours($maxAgeHours));

        if ($isExpired) {
            return null;
        }

        $refreshHours = (int) ($this->dynamic_refresh_hours ?: 24);
        $isStale = $lastUpdated ? $lastUpdated->lt(now()->subHours($refreshHours)) : false;

        return [
            'amount' => $amount,
            'currency' => $this->dynamic_price_currency ?: $baseCurrency,
            'source' => 'dynamic',
            'updated_at' => $lastUpdated?->toISOString(),
            'is_stale' => $isStale,
        ];
    }

    public function dynamicRefreshTriggerColumns(): array
    {
        return [
            'from_airport',
            'to_airport',
            'airline_id',
            'journey_type',
            'travel_class',
            'departure_plus_days',
            'stay_duration_days',
            'price_type',
        ];
    }

    public function getFromCityAttribute(): string
    {
        if ($this->relationLoaded('fromAirportModel') && $this->fromAirportModel) {
            return $this->fromAirportModel->city_name ?: $this->from_airport;
        }

        $airport = Airport::where('iata_code', $this->from_airport)->first();
        return $airport ? ($airport->city_name ?: $this->from_airport) : (string) $this->from_airport;
    }

    public function getToCityAttribute(): string
    {
        if ($this->relationLoaded('toAirportModel') && $this->toAirportModel) {
            return $this->toAirportModel->city_name ?: $this->to_airport;
        }

        $airport = Airport::where('iata_code', $this->to_airport)->first();
        return $airport ? ($airport->city_name ?: $this->to_airport) : (string) $this->to_airport;
    }

    public function fromAirportModel(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'from_airport', 'iata_code');
    }

    public function toAirportModel(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'to_airport', 'iata_code');
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_active', true)
              ->orWhereNull('is_active');
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')
                     ->orderBy('sort_order')
                     ->orderByDesc('id');
    }

    public function scopeDueForPriceRefresh(Builder $query): Builder
    {
        return $query->where('price_type', 'dynamic')
            ->where(function ($q) {
                $q->whereNull('next_price_refresh_at')
                  ->orWhere('next_price_refresh_at', '<=', now());
            });
    }
}
