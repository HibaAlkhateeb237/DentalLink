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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable(); // تم تحويله إلى string ليدعم 'stripe' وأي وسيلة أخرى مرنة

            // الأعمدة الجديدة الخاصة ببوابة الدفع Stripe
            $table->string('payment_intent_id')->nullable();
            $table->string('checkout_session_id')->nullable();
            $table->string('charge_id')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('currency', 3)->nullable(); // مثل USD, EUR

            // تعديل الحقل ليصبح nullable لأن الدفع لا يتم فوراً عند إنشاء السجل
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
