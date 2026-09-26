<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nomod_percentage_charge' => 'decimal:4',
            'nomod_fixed_charge' => 'decimal:8',
        ];
    }
}
