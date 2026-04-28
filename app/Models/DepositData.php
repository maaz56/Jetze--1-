<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class DepositData extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id'); // 'agent_id' links to 'users.id'
    }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($deposit) {
            // Generate a shorter unique ID with a length of 6 characters
            $shortUniqueId = strtoupper(Str::random(6)); // e.g., "A1B2C3"
            $deposit->receipt_reference = 'REF_ID-' . $shortUniqueId ;
        });
    }

}
