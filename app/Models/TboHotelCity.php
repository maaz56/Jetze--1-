<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TboHotelCity extends Model
{
    protected $fillable = [
        'country_code',
        'city_code',
        'name',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(TboHotelCountry::class, 'country_code', 'code');
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(TboHotel::class, 'city_code', 'city_code');
    }
}
