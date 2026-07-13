<?php

namespace App\Models;

use App\Jobs\RefreshPopularRoutePriceJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PopularRoute extends Model
{
    use HasFactory;

    protected $table = 'popular_routes';

    protected $fillable = [
        'from_airport',
        'to_airport',
        'type',
        'image',
        'airline_id',
        'journey_type',
        'travel_class',
        'departure_plus_days',
        'stay_duration_days',
        'price_type',
        'dynamic_refresh_hours',
        'static_price',
        'destination_name_en',
        'destination_name_ar',
        'blogs',
        'faqs',
    ];

     // Automatically append these accessors
    protected $appends = ['from_city', 'to_city'];

    protected $casts = [
        'blogs' => 'array',
        'faqs' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (PopularRoute $route) {
            $route->queueDynamicPriceRefresh();
        });

        static::updated(function (PopularRoute $route) {
            if ($route->wasChanged($route->dynamicRefreshTriggerColumns())) {
                $route->queueDynamicPriceRefresh();
            }
        });
    }

    public function queueDynamicPriceRefresh(): void
    {
        if ($this->price_type !== 'dynamic' || (int) $this->dynamic_refresh_hours <= 0) {
            return;
        }

        RefreshPopularRoutePriceJob::dispatch($this->id)->afterCommit();
    }

    public function queueDynamicPriceRefreshIfDue(): void
    {
        if (!$this->isDynamicPriceRefreshDue()) {
            return;
        }

        $lockKey = 'popular-route-refresh-queued-' . $this->id;
        if (!Cache::add($lockKey, true, now()->addMinutes(10))) {
            return;
        }

        RefreshPopularRoutePriceJob::dispatch($this->id)->afterCommit();
    }

    private function isDynamicPriceRefreshDue(): bool
    {
        if ($this->price_type !== 'dynamic' || (int) $this->dynamic_refresh_hours <= 0) {
            return false;
        }

        if ($this->static_price === null || $this->updated_at === null) {
            return true;
        }

        return $this->updated_at->lte(now()->subHours((int) $this->dynamic_refresh_hours));
    }

    private function dynamicRefreshTriggerColumns(): array
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
            'dynamic_refresh_hours',
        ];
    }

    // Accessor for from_city
    public function getFromCityAttribute()
    {
        $airport = Airport::where('iata_code', $this->from_airport)->first();
        return $airport ? $airport->city_name : $this->from_airport;
    }

    // Accessor for to_city
    public function getToCityAttribute()
    {
        $airport = Airport::where('iata_code', $this->to_airport)->first();
        return $airport ? $airport->city_name : $this->to_airport;
    }

    // Optional: define relationships
    public function fromAirport()
    {
        return $this->belongsTo(Airport::class, 'from_airport_id', 'iata_city_code');
    }

    public function toAirport()
    {
        return $this->belongsTo(Airport::class, 'to_airport_id' , 'iata_city_code');
    }

    public function airline()
    {
        return $this->belongsTo(Airline::class, 'airline_id', );
    }

    public function seo()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}
