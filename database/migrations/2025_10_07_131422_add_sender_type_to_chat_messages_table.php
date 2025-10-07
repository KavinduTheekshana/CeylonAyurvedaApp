<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // CRITICAL: Drop the foreign key constraint first
            // This is what's preventing therapists from sending messages
            $table->dropForeign(['sender_id']);
            
            // Add sender_type enum column after sender_id
            $table->enum('sender_type', ['patient', 'therapist'])
                  ->after('sender_id')
                  ->default('patient')
                  ->comment('Type of sender: patient or therapist');
            
            // Make sender_id nullable (will store either user_id or therapist_id)
            $table->unsignedBigInteger('sender_id')->nullable()->change();
            
            // Add composite index for better query performance
            $table->index(['sender_id', 'sender_type'], 'idx_sender_composite');
        });

        // Update existing messages to set sender_type to 'patient'
        // All existing messages should be from patients since therapist chat wasn't implemented
        DB::table('chat_messages')
            ->whereNull('sender_type')
            ->orWhere('sender_type', '')
            ->update(['sender_type' => 'patient']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Drop the composite index
            $table->dropIndex('idx_sender_composite');
            
            // Drop sender_type column
            $table->dropColumn('sender_type');
            
            // Make sender_id non-nullable again
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint to users table
            $table->foreign('sender_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
};