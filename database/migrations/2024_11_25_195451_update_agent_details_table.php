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
       
        // Drop the existing foreign key constraint
        Schema::table('agent_details', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // Drop the current foreign key
        });

        // Re-add the foreign key with ON DELETE CASCADE
        Schema::table('agent_details', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade'); // Add cascade deletion
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback to the previous foreign key without cascade
        Schema::table('agent_details', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // Drop the cascading foreign key

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users'); // Add foreign key without cascading
        });
    }
};
