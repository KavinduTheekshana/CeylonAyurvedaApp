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
        Schema::create('therapist_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')
                ->constrained('therapists')
                ->onDelete('cascade');
            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->onDelete('set null');
            $table->string('notification_type', 50);
            $table->string('title', 255);
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->enum('delivery_status', ['sent', 'delivered', 'failed', 'read'])
                ->default('sent');
            $table->string('fcm_message_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('therapist_id');
            $table->index('booking_id');
            $table->index('sent_at');
            $table->index(['therapist_id', 'delivery_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_notifications');
    }
};