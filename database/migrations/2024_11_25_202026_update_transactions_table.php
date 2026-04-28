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
       
        // Drop the existing foreign key
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Re-add the foreign key with cascading deletion
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade'); // Add cascading delete
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
        // Rollback to the previous foreign key without cascading delete
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users');
        });
    }
};
