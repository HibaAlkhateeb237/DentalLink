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

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Drop named foreign keys if they exist
            try {
                DB::statement('ALTER TABLE order_teeth DROP FOREIGN KEY ot_shade_id_fk');
            } catch (Throwable $e) {
                // ignore if not exists
            }

            try {
                DB::statement('ALTER TABLE order_teeth DROP FOREIGN KEY ot_dctp_id_fk');
            } catch (Throwable $e) {
                // ignore if not exists
            }

            Schema::table('order_teeth', function (Blueprint $table): void {
                if (Schema::hasColumn('order_teeth', 'tooth_shade_id')) {
                    $table->dropColumn('tooth_shade_id');
                }
                if (Schema::hasColumn('order_teeth', 'dental_compensation_type_price_id')) {
                    $table->dropColumn('dental_compensation_type_price_id');
                }
            });

            return;
        }

        // For SQLite (testing) -- recreate table without the two columns
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            // Determine which additional columns exist so we preserve them
            $preserve = ['id', 'order_id', 'tooth_number', 'notes', 'created_at', 'updated_at'];
            if (Schema::hasColumn('order_teeth', 'tooth_type')) {
                array_splice($preserve, 3, 0, 'tooth_type');
            }
            if (Schema::hasColumn('order_teeth', 'tooth_color')) {
                // insert before notes
                $pos = array_search('notes', $preserve, true);
                array_splice($preserve, $pos, 0, 'tooth_color');
            }

            // create new table with dynamic schema (exclude the two columns)
            Schema::create('order_teeth_new', function (Blueprint $table) use ($preserve) {
                $table->id();
                $table->foreignId('order_id')
                    ->constrained('orders')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('tooth_number');

                if (in_array('tooth_type', $preserve, true)) {
                    $table->string('tooth_type');
                }
                if (in_array('tooth_color', $preserve, true)) {
                    $table->string('tooth_color');
                }

                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'tooth_number']);
            });

            // copy only the preserved columns that actually exist
            $colsList = implode(', ', $preserve);
            DB::statement("INSERT INTO order_teeth_new ($colsList) SELECT $colsList FROM order_teeth");

            Schema::drop('order_teeth');
            Schema::rename('order_teeth_new', 'order_teeth');

            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_teeth')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('order_teeth', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_teeth', 'tooth_shade_id')) {
                    $table->foreignId('tooth_shade_id')
                        ->nullable()
                        ->after('tooth_number')
                        ->constrained('tooth_shades')
                        ->cascadeOnUpdate()
                        ->restrictOnDelete();
                }

                if (! Schema::hasColumn('order_teeth', 'dental_compensation_type_price_id')) {
                    $table->foreignId('dental_compensation_type_price_id')
                        ->nullable()
                        ->after('tooth_shade_id')
                        ->constrained('dental_compensation_type_prices')
                        ->cascadeOnUpdate()
                        ->restrictOnDelete();
                }
            });

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            // recreate table with the two columns
            Schema::create('order_teeth_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')
                    ->constrained('orders')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('tooth_number');
                $table->string('tooth_type');
                $table->string('tooth_color');
                $table->foreignId('tooth_shade_id')
                    ->nullable()
                    ->constrained('tooth_shades')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->foreignId('dental_compensation_type_price_id')
                    ->nullable()
                    ->constrained('dental_compensation_type_prices')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'tooth_number']);
            });

            // copy existing fields (new columns will be NULL)
            DB::statement('INSERT INTO order_teeth_new (id, order_id, tooth_number, tooth_type, tooth_color, notes, created_at, updated_at) SELECT id, order_id, tooth_number, tooth_type, tooth_color, notes, created_at, updated_at FROM order_teeth');

            Schema::drop('order_teeth');
            Schema::rename('order_teeth_new', 'order_teeth');

            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
};
