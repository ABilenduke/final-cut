<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticker_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label', 32);
            $table->string('text', 160);
            $table->string('href')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            // Same publish/window semantics as featured_slides.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticker_items');
    }
};
