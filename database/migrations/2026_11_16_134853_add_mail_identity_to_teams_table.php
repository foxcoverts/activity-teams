<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('mail_from_name')->nullable()->after('features');
            $table->string('mail_from_local')->nullable()->after('mail_from_name');
            $table->unique('mail_from_local');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropUnique(['mail_from_local']);
            $table->dropColumn(['mail_from_name', 'mail_from_local']);
        });
    }
};
