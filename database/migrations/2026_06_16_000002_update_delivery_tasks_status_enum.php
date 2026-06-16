<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE delivery_tasks MODIFY COLUMN status ENUM('empty', 'received', 'delivered', 'on_the_way_to_the_doctor', 'on_the_way_to_the_lab') NOT NULL DEFAULT 'empty'");
        }

        DB::table('delivery_tasks')
            ->where('status', 'en_route')
            ->where('direction', 'to_doctor')
            ->update(['status' => 'on_the_way_to_the_doctor']);

        DB::table('delivery_tasks')
            ->where('status', 'en_route')
            ->where('direction', 'to_lab')
            ->update(['status' => 'on_the_way_to_the_lab']);
    }

    public function down(): void
    {
        DB::table('delivery_tasks')
            ->whereIn('status', ['on_the_way_to_the_doctor', 'on_the_way_to_the_lab'])
            ->update(['status' => 'en_route']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE delivery_tasks MODIFY COLUMN status ENUM('empty', 'received', 'delivered', 'en_route') NOT NULL DEFAULT 'empty'");
        }
    }
};
