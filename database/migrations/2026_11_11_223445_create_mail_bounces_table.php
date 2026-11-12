<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_bounces', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('outbound_mail_id')->constrained('outbound_mails')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_message_id')->nullable();
            $table->string('category')->nullable();
            $table->string('action')->nullable();
            $table->text('message')->nullable();
            $table->boolean('hard')->default(false);
            $table->timestamp('bounced_at');
            $table->timestamps();

            $table->unique(['provider', 'provider_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_bounces');
    }
};
