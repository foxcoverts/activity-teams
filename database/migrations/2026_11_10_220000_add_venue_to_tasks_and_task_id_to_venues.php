<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUlid('venue_id')
                ->nullable()
                ->after('team_id')
                ->constrained('venues')
                ->nullOnDelete();
            $table->dropColumn('location');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->foreignUlid('task_id')
                ->nullable()
                ->after('booking_id')
                ->unique()
                ->constrained('tasks')
                ->nullOnDelete();
            $table->index(['task_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex(['task_id', 'name']);
            $table->dropUnique(['task_id']);
            $table->dropConstrainedForeignId('task_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
            $table->string('location')->nullable()->after('description');
        });
    }
};
