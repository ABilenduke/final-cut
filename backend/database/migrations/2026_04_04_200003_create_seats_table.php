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
            $table->string('label');
            $table->char('row', 1);
            $table->unsignedSmallInteger('number');
            $table->string('type')->default('standard');
            $table->timestamps();

            $table->unique(['auditorium_id', 'row', 'number']);
            $table->unique(['auditorium_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
