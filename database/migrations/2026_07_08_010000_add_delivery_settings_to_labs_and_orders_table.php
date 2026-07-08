<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->unsignedInteger('normal_delivery_days')->default(3);
            $table->unsignedInteger('urgent_delivery_days')->default(1);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('expected_delivery_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('expected_delivery_at');
        });

        Schema::table('labs', function (Blueprint $table) {
            $table->dropColumn(['normal_delivery_days', 'urgent_delivery_days']);
        });
    }
};
