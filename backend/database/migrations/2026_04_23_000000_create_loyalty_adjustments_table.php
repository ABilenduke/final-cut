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
            // Nullable so system-initiated adjustments (e.g. scheduled premier
            // expirations) can be recorded without an admin actor. Admin-facing
            // Filament actions always pass the authenticated admin user.
            // `nullOnDelete` preserves the audit row if the actor is later
            // deleted — losing attribution is acceptable; losing the audit is not.
            // FK references users.id (UUID). Column kept named `admin_user_id`
            // to preserve the semantic intent that the actor is an admin user;
            // by convention only Users with an AdminProfile populate this column.
            $table->foreignUuid('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
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
