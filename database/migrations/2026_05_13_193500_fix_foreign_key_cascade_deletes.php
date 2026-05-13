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
        Schema::disableForeignKeyConstraints();

        // Drop the existing foreign key with restrictOnDelete
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['dental_compensation_type_price_id']);
        });

        // Recreate with cascadeOnDelete
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('dental_compensation_type_price_id')
                ->references('id')
                ->on('dental_compensation_type_prices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['dental_compensation_type_price_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('dental_compensation_type_price_id')
                ->references('id')
                ->on('dental_compensation_type_prices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }
};
