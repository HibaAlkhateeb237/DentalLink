<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('requires_resubmission')->default(false)->after('remaining_amount');
            $table->text('resubmission_reason')->nullable()->after('requires_resubmission');
            $table->timestamp('resubmission_requested_at')->nullable()->after('resubmission_reason');
            $table->foreignId('resubmission_requested_by')
                ->nullable()
                ->after('resubmission_requested_at')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['requires_resubmission', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['resubmission_requested_by']);
            $table->dropIndex(['requires_resubmission', 'status']);
            $table->dropColumn([
                'requires_resubmission',
                'resubmission_reason',
                'resubmission_requested_at',
                'resubmission_requested_by',
            ]);
        });
    }
};
