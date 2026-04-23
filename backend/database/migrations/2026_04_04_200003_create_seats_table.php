<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auditorium_id')->constrained('auditoriums')->cascadeOnDelete();
            // FK constraint for section_id is added in the later
            // create_auditorium_sections_table migration (runs after this one),
            // because the auditorium_sections table doesn't exist yet at this point.
            $table->uuid('section_id')->nullable();
            $table->string('label');
            $table->char('row', 1);
            $table->unsignedSmallInteger('number');
            $table->string('type')->default('standard');
            $table->timestamp('unavailable_at')->nullable();
            $table->timestamps();

            $table->unique(['auditorium_id', 'row', 'number']);
            $table->unique(['auditorium_id', 'label']);
            $table->index(['section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
