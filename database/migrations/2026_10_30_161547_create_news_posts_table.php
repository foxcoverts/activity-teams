<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->longText('body');

            $table->timestamps();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->softDeletes();

            $table->index(['team_id', 'deleted_at', 'published_at']);
            $table->unique(['team_id', 'slug']);
        });

        DB::table('news_posts')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('notified_at')
            ->update(['notified_at' => DB::raw('published_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
