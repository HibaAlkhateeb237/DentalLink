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
        if (! Schema::hasColumn('dental_compensation_type_prices', 'currency')) {
            return;
        }

        Schema::table('dental_compensation_type_prices', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('dental_compensation_type_prices', 'currency')) {
            return;
        }

        Schema::table('dental_compensation_type_prices', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('base_price');
        });
    }
};
