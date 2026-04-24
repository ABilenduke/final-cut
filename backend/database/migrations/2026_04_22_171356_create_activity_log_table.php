<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');

            // Polymorphic subject — uses string columns so both bigint
            // (Movie, AdminUser) and UUID (Location, Auditorium, Seat,
            // Booking, Showtime) models can be addressed by the same
            // activity_log schema. Spatie's package compares the model's
            // key against the stored subject_id; string columns accept
            // both "42" and UUIDs without coercion errors.
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id'], 'subject');

            $table->string('event')->nullable();

            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->index(['causer_type', 'causer_id'], 'causer');

            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
