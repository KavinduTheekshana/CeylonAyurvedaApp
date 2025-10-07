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
        Schema::table('bookings', function (Blueprint $table) {
            // Add visit_type column
            // ENUM: 'home' or 'branch'
            // This determines if the service is at customer's home or at our branch
            $table->enum('visit_type', ['home', 'branch'])
                  ->after('location_id')
                  ->comment('Type of visit: home or branch');
            
            // Add home_visit_fee column
            // Stores the actual fee charged for home visits
            // NULL for branch visits (no fee)
            // DECIMAL(8,2) allows up to 999999.99
            $table->decimal('home_visit_fee', 8, 2)
                  ->nullable()
                  ->after('visit_type')
                  ->default(null)
                  ->comment('Fee charged for home visit service. NULL for branch visits');
            
            // Add index for faster queries filtering by visit type
            $table->index('visit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex(['visit_type']);
            
            // Drop the columns
            $table->dropColumn('visit_type');
            $table->dropColumn('home_visit_fee');
        });
    }
};