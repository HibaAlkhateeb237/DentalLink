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
        if (! Schema::hasTable('dental_compensation_type_prices')) {
            return;
        }

        $columns = Schema::getColumnListing('dental_compensation_type_prices');
        $isAlreadyNormalized = in_array('dental_compensation_type_id', $columns, true)
            && ! in_array('lab_id', $columns, true)
            && ! in_array('code', $columns, true);

        if ($isAlreadyNormalized) {
            return;
        }

        Schema::create('dental_compensation_type_prices_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dental_compensation_type_id')
                ->constrained('dental_compensation_types', indexName: 'dctp_new_type_id_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('base_price', 10, 2);
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dental_compensation_type_id', 'effective_from'], 'type_effective_unique');
            $table->index(['dental_compensation_type_id', 'is_active'], 'type_active_idx');
            $table->index(['dental_compensation_type_id', 'effective_from'], 'type_effective_idx');
        });

        $oldHasLegacyColumns = in_array('lab_id', $columns, true) && in_array('code', $columns, true);

        if ($oldHasLegacyColumns) {
            DB::table('dental_compensation_type_prices')
                ->orderBy('id')
                ->lazyById()
                ->each(function ($row): void {
                    $typeId = DB::table('dental_compensation_types')
                        ->where('lab_id', $row->lab_id)
                        ->where('code', $row->code)
                        ->value('id');

                    if ($typeId === null) {
                        $typeId = (int) DB::table('dental_compensation_types')->insertGetId([
                            'lab_id' => $row->lab_id,
                            'code' => $row->code,
                            'name' => $row->name,
                            'category' => $row->category,
                            'description' => $row->description,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('dental_compensation_type_prices_new')->updateOrInsert(
                        [
                            'dental_compensation_type_id' => $typeId,
                            'effective_from' => $row->effective_from,
                        ],
                        [
                            'base_price' => $row->base_price,
                            'is_active' => (bool) $row->is_active,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]
                    );
                });
        }

        Schema::drop('dental_compensation_type_prices');
        Schema::rename('dental_compensation_type_prices_new', 'dental_compensation_type_prices');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('dental_compensation_type_prices')) {
            return;
        }

        // Recreate the old denormalized structure (for rollback environments).
        Schema::create('dental_compensation_type_prices_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')
                ->constrained('labs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('code');
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('effective_from');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lab_id', 'code', 'effective_from'], 'lab_code_effective_unique');
            $table->index(['lab_id', 'code'], 'lab_code_idx');
            $table->index(['lab_id', 'is_active'], 'lab_active_idx');
            $table->index(['lab_id', 'effective_from'], 'lab_effective_idx');
        });

        DB::table('dental_compensation_type_prices')
            ->join('dental_compensation_types', 'dental_compensation_types.id', '=', 'dental_compensation_type_prices.dental_compensation_type_id')
            ->select([
                'dental_compensation_type_prices.id as id',
                'dental_compensation_types.lab_id as lab_id',
                'dental_compensation_types.code as code',
                'dental_compensation_types.name as name',
                'dental_compensation_types.category as category',
                'dental_compensation_type_prices.base_price as base_price',
                'dental_compensation_type_prices.currency as currency',
                'dental_compensation_type_prices.effective_from as effective_from',
                'dental_compensation_types.description as description',
                'dental_compensation_type_prices.is_active as is_active',
                'dental_compensation_type_prices.created_at as created_at',
                'dental_compensation_type_prices.updated_at as updated_at',
            ])
            ->orderBy('dental_compensation_type_prices.id')
            ->lazyById('id')
            ->each(function ($row): void {
                DB::table('dental_compensation_type_prices_old')->insert([
                    'id' => $row->id,
                    'lab_id' => $row->lab_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'category' => $row->category,
                    'base_price' => $row->base_price,
                    'currency' => $row->currency,
                    'effective_from' => $row->effective_from,
                    'description' => $row->description,
                    'is_active' => (bool) $row->is_active,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            });

        Schema::drop('dental_compensation_type_prices');
        Schema::rename('dental_compensation_type_prices_old', 'dental_compensation_type_prices');
    }
};
