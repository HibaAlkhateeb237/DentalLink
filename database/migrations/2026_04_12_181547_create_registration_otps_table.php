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
        Schema::create('registration_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('verify_attempts')->default(0);
            $table->timestamp('last_sent_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verification_token')->nullable()->unique();
            $table->timestamp('verification_token_expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_otps');
    }
};
