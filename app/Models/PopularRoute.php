<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PopularRoute extends Model
{
    use HasFactory;

    protected $table = 'popular_routes';

    protected $fillable = [
        'from_airport',
        'to_airport',
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
    ];

     // Automatically append these accessors
    protected $appends = ['from_city', 'to_city'];

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
}
