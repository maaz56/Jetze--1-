<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'commission_value' => 'decimal:2',
            'travel_start_date' => 'date:Y-m-d',
            'travel_end_date' => 'date:Y-m-d',
            'ticketing_start_date' => 'date:Y-m-d',
            'ticketing_end_date' => 'date:Y-m-d',
        ];
    }

    /** Return the airline targeted by this promotion, when one is configured. */
    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }
}
