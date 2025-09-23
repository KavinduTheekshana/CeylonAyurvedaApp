<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('therapist_id')->constrained('therapists')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Ensure one chat room per patient-therapist pair
            $table->unique(['patient_id', 'therapist_id']);
            
            // Indexes for performance
            $table->index(['patient_id', 'is_active']);
            $table->index(['therapist_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_rooms');
    }
};