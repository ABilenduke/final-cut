<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('US');
            $table->string('timezone');
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            // Business hours per day of week. Shape: { "monday": { "open": "HH:MM", "close": "HH:MM" }, ... }
            // Null day value means closed that day. Hours are local to the venue's `timezone` column.
            // Graduation criteria: if hours need per-showtime overrides, per-location-holiday exceptions,
            // or are queried for availability checks in core paths, graduate to a dedicated `location_hours` table.
            // jsonb (not json) — PostgreSQL's json type lacks an equality operator, which breaks
            // DISTINCT queries used by Filament's BelongsToMany select. jsonb is binary-comparable.
            $table->jsonb('hours')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
