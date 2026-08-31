<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceQuoteAdjustment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'configured_value' => 'decimal:8',
            'aed_amount' => 'decimal:8',
            'rule_snapshot' => 'array',
        ];
    }

    /** Return the quote that locked this commercial adjustment. */
    public function quote()
    {
        return $this->belongsTo(PriceQuote::class, 'price_quote_id');
    }
}
