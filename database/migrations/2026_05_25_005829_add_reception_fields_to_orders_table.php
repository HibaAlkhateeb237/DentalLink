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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('id');
            $table->string('patient_name')->nullable()->after('serial_number');
            $table->timestamp('received_at')->nullable()->after('patient_name');
            $table->timestamp('delivered_at')->nullable()->after('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'patient_name', 'received_at', 'delivered_at']);
        });
    }
};
