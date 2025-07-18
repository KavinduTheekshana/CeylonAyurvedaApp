<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('address');
            $table->string('city');
            $table->string('postcode');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->json('operating_hours')->nullable(); // Store opening hours
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('service_radius_miles')->default(5);
            $table->string('franchisee_name')->nullable();
            $table->string('franchisee_email')->nullable();
            $table->string('franchisee_phone')->nullable();
            $table->string('franchisee_photo')->nullable();
            $table->string('franchisee_activate_date')->nullable();
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};