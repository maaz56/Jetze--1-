<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SegmentMargin extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['excluded_airline_ids'];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'margin_value' => 'decimal:2',
        'airline_ids' => 'array',
        'disabled_airline_ids' => 'array',
    ];

    /** Expose one clear name while retaining the legacy database column. */
    public function getExcludedAirlineIdsAttribute(): array
    {
        return array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($this->disabled_airline_ids) ? $this->disabled_airline_ids : [],
        ))));
    }

    /** Return the legacy airline relation while old records are being migrated. */
    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }
}
