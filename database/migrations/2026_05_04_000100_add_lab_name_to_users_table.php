<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('lab_name')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'lab_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('lab_name');
            });
        }
    }
};
