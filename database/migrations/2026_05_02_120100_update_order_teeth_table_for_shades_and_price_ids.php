<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_teeth')) {
            return;
        }

        if (! Schema::hasColumn('order_teeth', 'tooth_shade_id')) {
            Schema::table('order_teeth', function (Blueprint $table): void {
                $table->foreignId('tooth_shade_id')
                    ->nullable()
                    ->after('tooth_number')
                    ->constrained('tooth_shades', indexName: 'ot_shade_id_fk')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('order_teeth', 'dental_compensation_type_price_id')) {
            Schema::table('order_teeth', function (Blueprint $table): void {
                $table->foreignId('dental_compensation_type_price_id')
                    ->nullable()
                    ->after('tooth_shade_id')
                    ->constrained('dental_compensation_type_prices', indexName: 'ot_dctp_id_fk')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('order_teeth', 'tooth_color')) {
            DB::table('order_teeth')
                ->select(['id', 'tooth_color'])
                ->orderBy('id')
                ->lazyById()
                ->each(function ($row): void {
                    $shadeId = DB::table('tooth_shades')
                        ->where('code', $row->tooth_color)
                        ->value('id');

                    if ($shadeId !== null) {
                        DB::table('order_teeth')
                            ->where('id', $row->id)
                            ->update(['tooth_shade_id' => $shadeId]);
                    }
                });

            Schema::table('order_teeth', function (Blueprint $table): void {
                $table->dropColumn('tooth_color');
            });
        }

        if (Schema::hasColumn('order_teeth', 'tooth_type')) {
            Schema::table('order_teeth', function (Blueprint $table): void {
                $table->dropColumn('tooth_type');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_teeth')) {
            return;
        }

        if (Schema::hasColumn('order_teeth', 'dental_compensation_type_price_id')) {
            Schema::table('order_teeth', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('dental_compensation_type_price_id');
            });
        }

        if (Schema::hasColumn('order_teeth', 'tooth_shade_id')) {
            Schema::table('order_teeth', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tooth_shade_id');
            });
        }

        Schema::table('order_teeth', function (Blueprint $table): void {
            $table->string('tooth_type')->nullable()->after('tooth_number');
            $table->string('tooth_color')->nullable()->after('tooth_type');
        });
    }
};
