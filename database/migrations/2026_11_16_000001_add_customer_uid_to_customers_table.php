<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_uid')->nullable()->unique()->after('id');
        });

        // Backfill existing customers in signup order: CUS_1001, CUS_1002, ...
        $next = Customer::FIRST_UID_NUMBER;
        Customer::query()->whereNull('customer_uid')->orderBy('id')->each(function ($customer) use (&$next) {
            $customer->forceFill(['customer_uid' => Customer::UID_PREFIX . $next++])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['customer_uid']);
            $table->dropColumn('customer_uid');
        });
    }
};
