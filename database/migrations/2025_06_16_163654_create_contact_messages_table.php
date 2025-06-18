<?php
// database/migrations/2024_01_01_000000_create_contact_messages_table.php

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
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('message');
            $table->string('name');
            $table->string('email');
            $table->unsignedBigInteger('branch_id');
            $table->string('branch_name');
            $table->boolean('is_guest')->default(false);
            $table->unsignedBigInteger('user_id')->nullable(); // For logged-in users
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed'])->default('pending');
            $table->text('admin_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->unsignedBigInteger('responded_by')->nullable(); // Admin user ID
            $table->json('metadata')->nullable(); // For additional data like user agent, IP, etc.
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('branch_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('responded_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index(['status', 'created_at']);
            $table->index(['branch_id', 'status']);
            $table->index(['user_id']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};