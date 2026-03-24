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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->string('keywords')->nullable();
            $table->string('tone');
            $table->string('audience');
            $table->string('depth');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt');
            $table->text('intro');
            $table->json('sections');
            $table->text('takeaway');
            $table->json('tags')->nullable();
            $table->unsignedTinyInteger('reading_time')->default(6);
            $table->string('model');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
