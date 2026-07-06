<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->string('original_order_status')->nullable()->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->dropColumn('original_order_status');
        });
    }
};
