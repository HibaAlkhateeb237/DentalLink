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
        Schema::create('dental_compensation_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')
                ->constrained('labs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->decimal('reference_price', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('lab_id');
            $table->unique(['lab_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dental_compensation_types');
    }
};
