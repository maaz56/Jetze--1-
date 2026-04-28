<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_travellers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('gender');
            $table->string('title', 10);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('date_of_birth');
            $table->string('nationality');
            $table->string('doc_type');
            $table->string('document_no');
            $table->string('expiry_date');
            $table->string('issue_country');
            $table->string('agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_travellers');
    }
};
