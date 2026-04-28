<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();

            // Blog content
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');

            // Optional extras
            $table->string('excerpt')->nullable();
            $table->string('featured_image')->nullable();

            // Status & publishing
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            // Author (optional)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};

