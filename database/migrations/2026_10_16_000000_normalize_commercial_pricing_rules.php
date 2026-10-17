<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add explicit rule controls and the columns needed for a reconciliable audit trail. */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('title');
            $table->integer('priority')->default(0)->after('is_active');
            $table->char('value_currency', 3)->default('AED')->after('commission_value');
        });

        Schema::table('segment_margins', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('title');
            $table->integer('priority')->default(0)->after('is_active');
            $table->char('value_currency', 3)->default('AED')->after('margin_value');
        });

        $this->disableAmbiguousLegacyFlatRules();
        $this->copyLegacyExcludedAirlines();

        Schema::table('price_quote_adjustments', function (Blueprint $table) {
            $table->unsignedSmallInteger('application_order')->nullable()->after('type');
            $table->string('application_scope', 40)->nullable()->after('calculation_type');
            $table->decimal('amount_before_aed', 20, 8)->nullable()->after('aed_amount');
            $table->decimal('amount_after_aed', 20, 8)->nullable()->after('amount_before_aed');
            $table->string('fare_reference')->nullable()->after('amount_after_aed');
            $table->string('flight_reference')->nullable()->after('fare_reference');
            $table->string('formula_version', 30)->nullable()->after('flight_reference');
        });
    }

    /** Remove only the schema added by this migration. */
    public function down(): void
    {
        Schema::table('price_quote_adjustments', function (Blueprint $table) {
            $table->dropColumn([
                'application_order',
                'application_scope',
                'amount_before_aed',
                'amount_after_aed',
                'fare_reference',
                'flight_reference',
                'formula_version',
            ]);
        });

        Schema::table('segment_margins', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'priority', 'value_currency']);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'priority', 'value_currency']);
        });
    }

    /** Preserve existing exclusions in the canonical disabled_airline_ids field. */
    private function copyLegacyExcludedAirlines(): void
    {
        DB::table('segment_margins')
            ->select(['id', 'airline_id', 'airline_ids', 'disabled_airline_ids'])
            ->orderBy('id')
            ->each(function (object $margin): void {
                $excluded = array_merge(
                    $this->decodeIds($margin->disabled_airline_ids),
                    $this->decodeIds($margin->airline_ids),
                    $margin->airline_id ? [(int) $margin->airline_id] : [],
                );

                DB::table('segment_margins')
                    ->where('id', $margin->id)
                    ->update([
                        'disabled_airline_ids' => json_encode(array_values(array_unique($excluded))),
                    ]);
            });
    }

    /** Require an admin review where old UI stored a value labelled as PKR. */
    private function disableAmbiguousLegacyFlatRules(): void
    {
        DB::table('promotions')
            ->where('commission_type', 'amount')
            ->update(['is_active' => false]);

        DB::table('segment_margins')->update(['is_active' => false]);
    }

    /** Decode one legacy JSON list into positive integer IDs. */
    private function decodeIds(mixed $value): array
    {
        $decoded = is_array($value) ? $value : json_decode($value ?: '[]', true);

        return array_values(array_filter(array_map('intval', is_array($decoded) ? $decoded : [])));
    }
};
