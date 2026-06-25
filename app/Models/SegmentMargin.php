<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SegmentMargin extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'airline_ids' => 'array',
        'disabled_airline_ids' => 'array',
    ];

    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }
}
