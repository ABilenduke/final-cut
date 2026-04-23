<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX admin_users_email_unique ON admin_users (lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
