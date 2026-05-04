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
        Schema::create('dental_compensation_type_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dental_compensation_type_id')
                ->constrained('dental_compensation_types', indexName: 'dctp_type_id_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('base_price', 10, 2);
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dental_compensation_type_id', 'effective_from'], 'type_effective_unique');
            $table->index(['dental_compensation_type_id', 'is_active'], 'type_active_idx');
            $table->index(['dental_compensation_type_id', 'effective_from'], 'type_effective_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dental_compensation_type_prices');
    }
};
