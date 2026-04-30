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
        Schema::create('lab_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')
                ->constrained('labs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->date('effective_from');

            $table->string('applies_to', 10); // order|item
            $table->string('kind', 30); // fixed_addon|multiplier|percent_discount
            $table->decimal('value', 10, 4);
            $table->boolean('per_unit')->default(false);
            $table->json('condition')->nullable();

            $table->integer('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lab_id', 'code', 'effective_from'], 'lab_rule_code_effective_unique');
            $table->index(['lab_id', 'is_active'], 'lab_rules_active_idx');
            $table->index(['lab_id', 'effective_from'], 'lab_rules_effective_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_pricing_rules');
    }
};
