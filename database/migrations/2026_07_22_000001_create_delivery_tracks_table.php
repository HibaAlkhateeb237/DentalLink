<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_tracks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_person_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('location_recorded_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index('status');
            $table->index('delivery_person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tracks');
    }
};
