<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // TMDB ID, NOT auto-increment
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('tagline')->nullable();
            $table->text('synopsis')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->date('release_date')->nullable();
            $table->json('genres')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->string('trailer_key')->nullable();
            $table->string('status')->default('now_showing');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
