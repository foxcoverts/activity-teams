<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->ulidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('digest_notified_at')->nullable();
            $table->uuid('outbound_message_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
