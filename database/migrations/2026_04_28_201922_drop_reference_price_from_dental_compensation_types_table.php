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
        if (! Schema::hasColumn('dental_compensation_types', 'reference_price')) {
            return;
        }

        Schema::table('dental_compensation_types', function (Blueprint $table) {
            $table->dropColumn('reference_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('dental_compensation_types', 'reference_price')) {
            return;
        }

        Schema::table('dental_compensation_types', function (Blueprint $table) {
            $table->decimal('reference_price', 10, 2)->after('name');
        });
    }
};
