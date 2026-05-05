<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labs', function (Blueprint $table): void {
            $table->decimal('rating', 3, 2)->default(0)->after('longitude');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('labs', 'rating')) {
            Schema::table('labs', function (Blueprint $table): void {
                $table->dropColumn('rating');
            });
        }
    }
};
