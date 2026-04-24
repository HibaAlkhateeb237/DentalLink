<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'lab_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('lab_id')->nullable()->after('location')->constrained('labs')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'lab_name')) {
            DB::table('users')
                ->join('labs', 'users.lab_name', '=', 'labs.name')
                ->whereNull('users.lab_id')
                ->update([
                    'users.lab_id' => DB::raw('labs.id'),
                ]);

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('lab_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'lab_name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('lab_name')->nullable()->after('location');
            });
        }

        DB::table('users')
            ->join('labs', 'users.lab_id', '=', 'labs.id')
            ->update([
                'users.lab_name' => DB::raw('labs.name'),
            ]);

        if (Schema::hasColumn('users', 'lab_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('lab_id');
            });
        }
    }
};
