<?php
// database/migrations/xxxx_xx_xx_create_user_fcm_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('fcm_token');
            $table->enum('device_type', ['android', 'ios']);
            $table->string('device_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'device_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};