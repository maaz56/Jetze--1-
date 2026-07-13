<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotDeal extends Model
{
    use HasFactory;

    protected $table = 'hot_deals';

    protected $fillable = [
        'from_airport',
        'to_airport',
        'title',
        'tag',
        'original_price',
        'discounted_price',
        'image_url',
        'is_active',
        'display_order',
        'start_date',
        'end_date',
    ];

    // Automatically append these accessors
    protected $appends = ['route', 'from_city', 'to_city', 'discount_percentage'];

    protected $casts = [
        'original_price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    // Accessor for route (combines from and to cities)
    public function getRouteAttribute()
    {
        return $this->from_city . ' → ' . $this->to_city;
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

    // Relationships
    public function fromAirport()
    {
        return $this->belongsTo(Airport::class, 'from_airport', 'iata_code');
    }

    public function toAirport()
    {
        return $this->belongsTo(Airport::class, 'to_airport', 'iata_code');
    }

    public function seo()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id', 'desc');
    }

    // Accessor for discount percentage
    public function getDiscountPercentageAttribute()
    {
        if ($this->original_price <= 0) return 0;
        return round((($this->original_price - $this->discounted_price) / $this->original_price) * 100);
    }
}
