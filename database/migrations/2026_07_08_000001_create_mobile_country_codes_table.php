<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mobile_country_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('dial_code', 10);
            $table->char('code', 2)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_country_codes');
    }
};
