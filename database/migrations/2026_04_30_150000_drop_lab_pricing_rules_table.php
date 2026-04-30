<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('lab_pricing_rules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank.
        // If you need pricing rules again, restore the original create migration.
    }
};
