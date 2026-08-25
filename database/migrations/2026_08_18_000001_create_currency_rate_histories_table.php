<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('currency_code', 3)->index();
            $table->decimal('old_rate', 20, 8)->nullable();
            $table->decimal('new_rate', 20, 8);
            $table->text('reason');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['currency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rate_histories');
    }
};
