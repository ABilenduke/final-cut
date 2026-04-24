<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorium_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auditorium_id')->constrained('auditoriums')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_multiplier', 5, 2)->default(1.00);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['auditorium_id', 'name']);
        });

        // `seats.section_id` was added by the create_seats_table migration but
        // could not carry its foreign-key constraint there — this table didn't
        // exist yet. Wire the FK now that both tables are in place.
        Schema::table('seats', function (Blueprint $table) {
            $table->foreign('section_id')
                ->references('id')
                ->on('auditorium_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
        });
        Schema::dropIfExists('auditorium_sections');
    }
};
