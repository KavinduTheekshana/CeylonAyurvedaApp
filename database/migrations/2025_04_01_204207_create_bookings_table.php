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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Nullable for guest bookings
            $table->unsignedBigInteger('service_id');
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date');
            $table->time('time');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('postcode');
            $table->foreignId('therapist_id')->nullable()->constrained()->onDelete('set null');
            $table->text('notes')->nullable();

            // Pricing fields
            $table->decimal('price', 8, 2); // Final price after discount
            $table->decimal('original_price', 8, 2)->nullable(); // Original price before discount
            $table->decimal('discount_amount', 8, 2)->nullable(); // Discount amount applied

            // Coupon fields
            $table->foreignId('coupon_id')->nullable()->constrained()->onDelete('set null');
            $table->string('coupon_code')->nullable(); // Store the code used

            // Payment 
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->string('payment_method')->nullable(); // card, bank_transfer
            $table->timestamp('paid_at')->nullable();

            $table->string('reference')->unique();
            $table->string('status')->default('confirmed');

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('service_id')->references('id')->on('services');

            $table->timestamps();

            // Indexes for better performance
            $table->index(['status', 'date']);
            $table->index(['therapist_id', 'date', 'time']);
            $table->index(['user_id', 'status']);
            $table->index('reference');
            $table->index('coupon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
