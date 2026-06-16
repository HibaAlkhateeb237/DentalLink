<?php

use App\Support\DeliveryTaskDirection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->enum('direction', DeliveryTaskDirection::ALL)
                ->default(DeliveryTaskDirection::TO_LAB)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->dropColumn('direction');
        });
    }
};
