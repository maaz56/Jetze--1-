<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingPriceSnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['commercial_reconciliation'];

    protected function casts(): array
    {
        return [
            'provider_amount' => 'decimal:8',
            'provider_gross_amount' => 'decimal:8',
            'provider_rate_to_aed' => 'decimal:8',
            'provider_aed_amount' => 'decimal:8',
            'provider_gross_aed_amount' => 'decimal:8',
            'selling_amount' => 'decimal:8',
            'selling_rate_to_aed' => 'decimal:8',
            'aed_amount' => 'decimal:8',
            'adjustments_snapshot' => 'array',
        ];
    }

    /**
     * Get the booking whose financial values were locked in this snapshot.
     */
    public function booking()
    {
        return $this->belongsTo(FlightBookings::class, 'booking_id');
    }

    /**
     * Get the temporary quote from which this permanent snapshot was created.
     */
    public function priceQuote()
    {
        return $this->belongsTo(PriceQuote::class);
    }

    /** Return a permanent AED reconciliation summary for admin audit screens. */
    public function getCommercialReconciliationAttribute(): ?array
    {
        if ($this->provider_aed_amount === null || $this->aed_amount === null) {
            return null;
        }

        $adjustmentTotal = '0';
        foreach ((array) $this->adjustments_snapshot as $adjustment) {
            $adjustmentTotal = bcadd($adjustmentTotal, (string) ($adjustment['aed_amount'] ?? '0'), 8);
        }

        $expectedSelling = bcadd((string) $this->provider_aed_amount, $adjustmentTotal, 8);
        $difference = bcsub((string) $this->aed_amount, $expectedSelling, 8);
        $absoluteDifference = str_starts_with($difference, '-') ? substr($difference, 1) : $difference;

        return [
            'provider_cost_aed' => (string) $this->provider_aed_amount,
            'adjustment_total_aed' => $adjustmentTotal,
            'expected_selling_aed' => $expectedSelling,
            'locked_selling_aed' => (string) $this->aed_amount,
            'difference_aed' => $difference,
            'is_reconciled' => bccomp($absoluteDifference, '0.01000000', 8) <= 0,
        ];
    }
}
