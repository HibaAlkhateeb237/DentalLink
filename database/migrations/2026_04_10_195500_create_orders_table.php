<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('lab_id')
                ->constrained('labs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('qr_code')->unique();
            $table->enum('priority', ['normal', 'urgent'])->default('normal');
            $table->enum('status', ['pending', '=', 'in_progress', 'completed', 'delivered', 'cancelled'])->default('pending');
            $table->enum('order_type', ['digital', 'physical', 'hybrid'])->default('digital');
            $table->text('notes')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['lab_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
