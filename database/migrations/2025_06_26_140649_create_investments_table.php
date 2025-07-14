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
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2); // Investment amount in GBP
            $table->string('currency', 3)->default('GBP');
            $table->string('payment_method')->default('card');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_payment_method_id')->nullable();
            $table->string('reference')->unique(); // Unique investment reference
            $table->text('notes')->nullable();
            $table->timestamp('invested_at')->nullable(); // When investment was completed
            $table->json('stripe_metadata')->nullable(); // Store additional Stripe data
            $table->text('bank_transfer_details')->nullable(); // Admin notes for bank transfer
            $table->timestamp('bank_transfer_confirmed_at')->nullable(); // When bank transfer was confirmed
            $table->foreignId('confirmed_by_admin_id')->nullable()->constrained('users'); // Which admin confirmed
            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'location_id']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_method', 'status']);
            $table->index('reference');
        });

        // Create location investments table to track total investments per location
        Schema::create('location_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('total_invested', 10, 2)->default(0); // Total amount invested
            $table->decimal('investment_limit', 10, 2)->default(10000); // Max investment limit
            $table->integer('total_investors')->default(0); // Count of unique investors
            $table->boolean('is_open_for_investment')->default(true);
            $table->timestamps();
        });

        // Create investment transactions table for tracking all payment activities
        Schema::create('investment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['payment', 'refund', 'partial_refund']);
            $table->decimal('amount', 10, 2);
            $table->string('stripe_transaction_id')->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed', 'canceled'])->default('pending');
            $table->json('stripe_response')->nullable(); // Store full Stripe response
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['investment_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');
        Schema::dropIfExists('location_investments');
        Schema::dropIfExists('investments');
    }
};