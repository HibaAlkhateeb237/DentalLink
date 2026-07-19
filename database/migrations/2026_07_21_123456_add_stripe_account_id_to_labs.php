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
        Schema::table('labs', function (Blueprint $table) {
            // Add stripe_account_id column only
            // This field is needed to store Stripe Express Connected Account IDs
            // Without it, we cannot:
            // - Link laboratories to their Stripe accounts
            // - Process payments using Stripe Connect
            // - Enable lab onboarding through Stripe Account Links
            // - Verify that a lab has completed Stripe onboarding
            $table->string('stripe_account_id')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->dropColumn('stripe_account_id');
        });
    }
};
