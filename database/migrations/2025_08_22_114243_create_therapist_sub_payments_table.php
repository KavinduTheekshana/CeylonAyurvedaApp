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
        Schema::create('therapist_sub_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_sub_id')->constrained()->onDelete('cascade');
            $table->string('stripe_payment_intent_id')->unique();
            $table->string('stripe_invoice_id')->nullable();
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('GBP');
            $table->enum('status', ['succeeded', 'pending', 'failed', 'canceled', 'refunded'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('stripe_payment_data')->nullable(); // Full Stripe payment data
            $table->timestamps();

            $table->index(['therapist_sub_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_sub_payments');
    }
};
