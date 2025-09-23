<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('message_content');
            
            // Encryption fields
            $table->text('encrypted_content')->nullable();
            $table->string('encryption_algorithm')->nullable();
            $table->string('key_id')->nullable();
            $table->string('initialization_vector')->nullable();
            
            $table->enum('message_type', ['text', 'image', 'file'])->default('text');
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['chat_room_id', 'sent_at']);
            $table->index(['sender_id', 'sent_at']);
            $table->index(['chat_room_id', 'is_read']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_messages');
    }
};