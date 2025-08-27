<?php
// database/migrations/2025_01_27_000000_add_preferences_to_therapists_table.php

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
        Schema::table('therapists', function (Blueprint $table) {
            // Service User Preferences
            $table->enum('preferred_gender', ['all', 'male', 'female'])->default('all')->after('online_status');
            $table->integer('age_range_start')->default(18)->after('preferred_gender');
            $table->integer('age_range_end')->default(65)->after('age_range_start');
            $table->string('preferred_language')->default('english')->after('age_range_end');
            
            // Service Delivery Preferences
            $table->boolean('accept_new_patients')->default(true)->after('preferred_language');
            $table->boolean('home_visits_only')->default(false)->after('accept_new_patients');
            $table->boolean('clinic_visits_only')->default(false)->after('home_visits_only');
            $table->integer('max_travel_distance')->default(10)->after('clinic_visits_only'); // in miles
            $table->boolean('weekends_available')->default(false)->after('max_travel_distance');
            $table->boolean('evenings_available')->default(false)->after('weekends_available');
            
            // Timestamps for preferences
            $table->timestamp('preferences_updated_at')->nullable()->after('evenings_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_gender',
                'age_range_start', 
                'age_range_end',
                'preferred_language',
                'accept_new_patients',
                'home_visits_only',
                'clinic_visits_only',
                'max_travel_distance',
                'weekends_available',
                'evenings_available',
                'preferences_updated_at'
            ]);
        });
    }
};