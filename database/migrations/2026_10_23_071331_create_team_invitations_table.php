<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // A person with no email address (a placeholder) must never be able to sign in.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_passwordless_without_email CHECK (email IS NOT NULL OR password IS NULL)');

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64);
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_passwordless_without_email');

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
