<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_adjustments', function (Blueprint $table) {
            $table->id();
            // users.id is a UUID, so foreignUuid is required.
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('admin_users');
            $table->integer('points_delta');
            $table->text('reason');
            $table->string('change_type');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_adjustments');
    }
};
