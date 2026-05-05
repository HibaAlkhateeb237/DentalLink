<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add fields to orders table
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('tooth_shade_id')
                ->nullable()
                ->after('order_type')
                ->constrained('tooth_shades')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('dental_compensation_type_price_id')
                ->nullable()
                ->after('tooth_shade_id')
                ->constrained('dental_compensation_type_prices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // NOTE: We are NOT removing the columns from order_teeth table because:
        // 1. MySQL requires explicit FK drops which are complex for migrations
        // 2. SQLite requires table recreation which is expensive
        // 3. The application will use the order-level references and ignore tooth-level ones
        // 4. This maintains backward compatibility and allows gradual migration
    }

    public function down(): void
    {
        // Remove fields from orders table
        Schema::disableForeignKeyConstraints();

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['tooth_shade_id', 'dental_compensation_type_price_id']);
        });

        Schema::enableForeignKeyConstraints();
    }
};
