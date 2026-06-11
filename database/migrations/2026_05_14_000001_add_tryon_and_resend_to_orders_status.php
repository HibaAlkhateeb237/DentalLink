<?php

use App\Support\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Change enum to include new statuses. Requires doctrine/dbal to change enums.
            $table->enum('status', OrderStatus::ALL)->default(OrderStatus::PENDING)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'delivered',
            ])->default('pending')->change();
        });
    }
};
