<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 160);
            $table->string('slug')->unique();
            $table->text('excerpt');
            $table->string('author_name', 120);
            $table->string('image_url')->nullable();
            // Plain text; blank lines delimit paragraphs (the public page
            // splits on \n\n) — deliberately not markdown.
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
