<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('news_posts')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('notified_at')
            ->update(['notified_at' => DB::raw('published_at')]);
    }
};
