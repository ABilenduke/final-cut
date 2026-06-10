<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->text('description');
            // Cents, like every monetary value in the system. "From $350"
            // marketing display happens at the frontend boundary.
            $table->unsignedInteger('starting_price');
            $table->json('features');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_packages');
    }
};
