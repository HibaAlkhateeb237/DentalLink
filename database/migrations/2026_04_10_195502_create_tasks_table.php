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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('assigned');

            // ['assigned', 'in_progress','completed']
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
