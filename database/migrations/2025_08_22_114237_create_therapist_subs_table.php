<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('therapist_subs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained()->onDelete('cascade');
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_customer_id');
            $table->string('stripe_price_id'); // Stripe price ID for £199/month
            $table->enum('status', ['active', 'canceled', 'past_due', 'unpaid', 'incomplete', 'trialing'])->default('incomplete');
            $table->decimal('amount', 8, 2)->default(199.00); // £199/month
            $table->string('currency', 3)->default('GBP');
            $table->string('interval', 10)->default('month'); // month/year
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_start')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // When subscription actually ends
            $table->json('stripe_metadata')->nullable(); // Additional Stripe data
            $table->timestamps();

            // Indexes for performance
            $table->index(['therapist_id', 'status']);
            $table->index(['status', 'current_period_end']);
            $table->index('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_subscriptions');
    }
};
