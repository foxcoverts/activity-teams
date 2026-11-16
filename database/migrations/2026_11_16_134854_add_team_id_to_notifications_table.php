<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignUlid('team_id')->nullable()->after('notifiable_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
