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
        Schema::create('therapist_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')
                ->constrained('therapists')
                ->onDelete('cascade');
            $table->text('fcm_token');
            $table->enum('device_type', ['android', 'ios']);
            $table->string('device_id', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('therapist_id');
            $table->index(['therapist_id', 'is_active']);
            $table->unique(['therapist_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_fcm_tokens');
    }
};