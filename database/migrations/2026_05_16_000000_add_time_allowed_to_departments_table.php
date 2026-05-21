<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->integer('time_allowed')->nullable()->comment('Time allowed in hours for tasks in this department');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasColumn('departments', 'time_allowed')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('time_allowed');
            });
        }
    }
};