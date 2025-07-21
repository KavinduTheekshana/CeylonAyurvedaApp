<?php
// database/migrations/2025_01_21_create_coupons_table.php

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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->enum('type', ['percentage', 'fixed']); // percentage or fixed amount
            $table->decimal('value', 10, 2); // percentage (0-100) or fixed amount
            $table->decimal('minimum_amount', 10, 2)->nullable(); // minimum purchase amount
            $table->integer('usage_limit')->nullable(); // total usage limit
            $table->integer('usage_count')->default(0); // current usage count
            $table->integer('usage_limit_per_user')->nullable(); // usage limit per user
            $table->boolean('is_active')->default(true);
            $table->datetime('valid_from');
            $table->datetime('valid_until')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['code', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};