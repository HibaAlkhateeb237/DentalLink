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
        Schema::create('portfolio_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('case_name');
            $table->string('before_image_path');
            $table->string('after_image_path');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_cases');
    }
};
