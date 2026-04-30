<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dental_compensation_types', function (Blueprint $table) {
            $table->string('code')->nullable()->after('lab_id');
            $table->string('category')->nullable()->after('name');
        });

        // Backfill codes for existing rows to avoid nulls in practice.
        DB::table('dental_compensation_types')
            ->select(['id', 'lab_id', 'name', 'code'])
            ->orderBy('id')
            ->lazyById()
            ->each(function ($row): void {
                if (! empty($row->code)) {
                    return;
                }

                $base = Str::slug((string) $row->name, '_');
                $base = $base === '' ? 'type' : $base;
                $code = $base;

                $exists = DB::table('dental_compensation_types')
                    ->where('lab_id', $row->lab_id)
                    ->where('code', $code)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if ($exists) {
                    $code = $base.'_'.$row->id;
                }

                DB::table('dental_compensation_types')
                    ->where('id', $row->id)
                    ->update(['code' => $code]);
            });

        Schema::table('dental_compensation_types', function (Blueprint $table) {
            $table->unique(['lab_id', 'code'], 'dental_comp_types_lab_code_unique');
            $table->index(['lab_id', 'code'], 'dental_comp_types_lab_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dental_compensation_types', function (Blueprint $table) {
            $table->dropIndex('dental_comp_types_lab_code_idx');
            $table->dropUnique('dental_comp_types_lab_code_unique');
            $table->dropColumn(['code', 'category']);
        });
    }
};
