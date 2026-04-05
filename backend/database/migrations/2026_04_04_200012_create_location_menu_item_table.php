<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_menu_item', function (Blueprint $table) {
            $table->uuid('location_id');
            $table->uuid('menu_item_id');
            $table->unsignedInteger('price_override')->nullable();
            $table->timestamp('unavailable_at')->nullable();
            $table->timestamps();

            $table->primary(['location_id', 'menu_item_id']);
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_menu_item');
    }
};
