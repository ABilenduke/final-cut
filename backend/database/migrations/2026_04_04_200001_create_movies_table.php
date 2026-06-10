<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('tagline')->nullable();
            $table->text('synopsis')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->date('release_date')->nullable();
            $table->json('genres')->nullable();
            $table->json('cast')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->string('trailer_key')->nullable();
            $table->string('status')->default('now_showing');
            $table->timestamp('tmdb_enriched_at')->nullable();
            // Home hero curation flag (admin-v2 Plan 16) — at most one movie set.
            $table->timestamp('home_featured_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
