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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained()->onDelete('cascade'); // Foreign key to treatments
            $table->string('title'); // Service Title
            $table->string('image'); // Image
            $table->string('subtitle')->nullable(); // Subtitle
            $table->decimal('price', 8, 2)->nullable(); // Price
            $table->integer('duration')->nullable(); // Time Duration (minutes)
            $table->text('benefits')->nullable(); // Benefits
            $table->text('description')->nullable(); // Description
            $table->boolean('status')->default(1); // Status (Active/Inactive)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
