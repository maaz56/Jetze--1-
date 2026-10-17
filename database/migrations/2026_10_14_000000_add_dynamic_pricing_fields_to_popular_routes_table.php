<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('popular_routes', function (Blueprint $table) {
            if (!Schema::hasColumn('popular_routes', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('popular_routes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('type');
            }
            if (!Schema::hasColumn('popular_routes', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('popular_routes', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_featured');
            }
            if (!Schema::hasColumn('popular_routes', 'dynamic_price')) {
                $table->decimal('dynamic_price', 12, 2)->nullable()->after('static_price');
            }
            if (!Schema::hasColumn('popular_routes', 'dynamic_price_currency')) {
                $table->char('dynamic_price_currency', 3)->nullable()->default('AED')->after('dynamic_price');
            }
            if (!Schema::hasColumn('popular_routes', 'dynamic_price_meta')) {
                $table->json('dynamic_price_meta')->nullable()->after('dynamic_price_currency');
            }
            if (!Schema::hasColumn('popular_routes', 'price_checked_at')) {
                $table->timestamp('price_checked_at')->nullable()->after('dynamic_price_meta');
            }
            if (!Schema::hasColumn('popular_routes', 'price_updated_at')) {
                $table->timestamp('price_updated_at')->nullable()->after('price_checked_at');
            }
            if (!Schema::hasColumn('popular_routes', 'next_price_refresh_at')) {
                $table->timestamp('next_price_refresh_at')->nullable()->index()->after('price_updated_at');
            }
            if (!Schema::hasColumn('popular_routes', 'price_status')) {
                $table->string('price_status', 32)->default('pending')->after('next_price_refresh_at');
            }
            if (!Schema::hasColumn('popular_routes', 'price_failure_count')) {
                $table->unsignedSmallInteger('price_failure_count')->default(0)->after('price_status');
            }
            if (!Schema::hasColumn('popular_routes', 'price_last_error')) {
                $table->string('price_last_error', 500)->nullable()->after('price_failure_count');
            }
        });

        // Backfill slugs and dynamic_price for existing routes
        $routes = DB::table('popular_routes')->get();
        foreach ($routes as $route) {
            $updates = [];

            if (empty($route->slug)) {
                $base = Str::slug(($route->destination_name_en ?: 'flight') . '-' . $route->from_airport . '-' . $route->to_airport);
                $slug = $base;
                $counter = 1;
                while (DB::table('popular_routes')->where('slug', $slug)->where('id', '!=', $route->id)->exists()) {
                    $slug = "{$base}-{$counter}";
                    $counter++;
                }
                $updates['slug'] = $slug;
            }

            if ($route->price_type === 'dynamic') {
                if ($route->static_price !== null && empty($route->dynamic_price)) {
                    $updates['dynamic_price'] = $route->static_price;
                    $updates['dynamic_price_currency'] = 'AED';
                    $updates['price_updated_at'] = $route->updated_at ?: now();
                    $updates['price_checked_at'] = $route->updated_at ?: now();
                    $updates['price_status'] = 'fresh';
                }
                if (empty($route->next_price_refresh_at)) {
                    $updates['next_price_refresh_at'] = now();
                }
            }

            if (!empty($updates)) {
                DB::table('popular_routes')->where('id', $route->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('popular_routes', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'is_active',
                'is_featured',
                'sort_order',
                'dynamic_price',
                'dynamic_price_currency',
                'dynamic_price_meta',
                'price_checked_at',
                'price_updated_at',
                'next_price_refresh_at',
                'price_status',
                'price_failure_count',
                'price_last_error',
            ]);
        });
    }
};
