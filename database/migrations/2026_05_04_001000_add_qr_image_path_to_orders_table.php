<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('qr_image_path')->nullable()->after('qr_code');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'qr_image_path')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('qr_image_path');
            });
        }
    }
};
