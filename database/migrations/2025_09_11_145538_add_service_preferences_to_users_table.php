<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Service User Preferences
            $table->enum('preferred_therapist_gender', ['any', 'male', 'female'])->default('any');
            $table->string('preferred_language')->default('english');
            $table->integer('preferred_age_range_therapist_start')->default(25);
            $table->integer('preferred_age_range_therapist_end')->default(65);
            $table->timestamp('preferences_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_therapist_gender',
                'preferred_language', 
                'preferred_age_range_therapist_start',
                'preferred_age_range_therapist_end',
                'preferences_updated_at'
            ]);
        });
    }
};