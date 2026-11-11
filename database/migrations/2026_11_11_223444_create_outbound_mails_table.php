<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_mails', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('message_id');
            $table->string('recipient');
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('mailable')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'recipient']);
            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_mails');
    }
};
