<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TboHotel extends Model
{
    protected $fillable = [
        'hotel_code',
        'hotel_name',
        'hotel_rating',
        'address',
        'country_code',
        'country_name',
        'city_code',
        'city_name',
        'map',
        'latitude',
        'longitude',
        'images',
        'facilities',
        'description',
        'raw_response',
        'search_text',
    ];

    protected $casts = [
        'images' => 'array',
        'facilities' => 'array',
        'raw_response' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(TboHotelCity::class, 'city_code', 'city_code');
    }
}
