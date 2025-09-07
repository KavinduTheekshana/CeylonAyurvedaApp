<?php
// database/migrations/xxxx_xx_xx_create_notifications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['promotional', 'system']);
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('sent_at')->nullable();
            $table->integer('total_sent')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['type', 'sent_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};