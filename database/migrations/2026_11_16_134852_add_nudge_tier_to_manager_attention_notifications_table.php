<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_attention_notifications', function (Blueprint $table): void {
            $table->string('nudge_tier')->nullable()->after('triage');
        });
    }

    public function down(): void
    {
        Schema::table('manager_attention_notifications', function (Blueprint $table): void {
            $table->dropColumn('nudge_tier');
        });
    }
};
