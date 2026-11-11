<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_bounced_at')->nullable()->after('email_verified_at');
            $table->string('email_bounce_category')->nullable()->after('email_bounced_at');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('outbound_message_id');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('outbound_message_id')->nullable()->after('digest_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_bounced_at', 'email_bounce_category']);
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('outbound_message_id');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->uuid('outbound_message_id')->nullable()->after('digest_notified_at');
        });
    }
};
