<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TboHotelCountry extends Model
{
    protected $fillable = [
        'code',
        'name',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];

    public function cities(): HasMany
    {
        return $this->hasMany(TboHotelCity::class, 'country_code', 'code');
    }
}
