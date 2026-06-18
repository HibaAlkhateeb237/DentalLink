<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('lab_id')
                ->nullable()
                ->constrained('labs')
                ->cascadeOnDelete();

            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['name', 'guard_name', 'lab_id']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name', 'lab_id']);
            $table->unique(['name', 'guard_name']);

            $table->dropForeign(['lab_id']);
            $table->dropColumn('lab_id');
        });
    }
};
