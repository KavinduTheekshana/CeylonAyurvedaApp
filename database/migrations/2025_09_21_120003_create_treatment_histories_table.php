<?php
// database/migrations/2025_01_20_000000_create_treatment_histories_table.php

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
        Schema::create('treatment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('therapist_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->string('patient_name'); // Store patient name for easy reference
            $table->text('treatment_notes'); // Main treatment notes
            $table->text('observations')->nullable(); // Patient observations
            $table->text('recommendations')->nullable(); // Post-treatment recommendations
            $table->enum('patient_condition', ['improved', 'same', 'worse'])->nullable();
            $table->integer('pain_level_before')->nullable(); // 1-10 scale
            $table->integer('pain_level_after')->nullable(); // 1-10 scale
            $table->json('areas_treated')->nullable(); // JSON array of body areas
            $table->text('next_treatment_plan')->nullable();
            $table->boolean('is_editable')->default(true); // Will be set to false after 24 hours
            $table->timestamp('treatment_completed_at');
            $table->timestamp('edit_deadline_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['therapist_id', 'treatment_completed_at']);
            $table->index(['booking_id']);
            $table->index(['is_editable', 'edit_deadline_at']);
            
            // Ensure one treatment history per booking
            $table->unique('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_histories');
    }
};