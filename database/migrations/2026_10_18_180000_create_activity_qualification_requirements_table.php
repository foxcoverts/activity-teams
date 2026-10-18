<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_qualification_requirements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('activity_id')->constrained()->cascadeOnDelete();
            $table->string('detail_type');
            $table->json('permit_activity')->nullable();
            $table->json('category')->nullable();
            $table->json('permit_type')->nullable();
            $table->json('award')->nullable();
            $table->json('scheme')->nullable();
            $table->json('level')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_qualification_requirements');
    }
};
