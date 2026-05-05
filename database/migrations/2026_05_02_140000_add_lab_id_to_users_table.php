<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('lab_id')
                ->nullable()
                ->after('birthdate')
                ->constrained()
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('lab_id');
        });

        Schema::enableForeignKeyConstraints();
    }
};
