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
        Schema::create('lab_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')
                ->constrained('labs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('currency', 3)->default('USD');
            $table->date('effective_from');

            $table->decimal('implant_addon', 10, 2)->default(2.50);
            $table->decimal('long_bridge_or_high_addon', 10, 2)->default(3.50);
            $table->decimal('lisi_connect_etching_addon', 10, 2)->default(2.00);
            $table->decimal('intraoral_print_fee', 10, 2)->default(8.00);
            $table->decimal('vip_urgent_multiplier', 10, 4)->default(1.2500);

            $table->text('student_discount_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lab_id', 'effective_from']);
            $table->index(['lab_id', 'is_active']);
            $table->index(['lab_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_pricing_settings');
    }
};
