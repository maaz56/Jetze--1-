<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'price' => 'array',  // This will automatically cast the price JSON string to an array
    ];

    public function slices()
    {
        return $this->hasMany(Slice::class, 'flight_id');
    }
}


