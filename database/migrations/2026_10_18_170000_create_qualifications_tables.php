<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_permits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('activity');
            $table->string('category')->nullable();
            $table->string('permit_type');
            $table->text('restrictions')->nullable();
            $table->timestamps();
        });

        Schema::create('mountain_training_qualifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('award');
            $table->timestamps();
        });

        Schema::create('girlguiding_qualifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scheme');
            $table->string('level');
            $table->timestamps();
        });

        Schema::create('qualifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('detail');
            $table->date('expires_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualifications');
        Schema::dropIfExists('girlguiding_qualifications');
        Schema::dropIfExists('mountain_training_qualifications');
        Schema::dropIfExists('scout_permits');
    }
};
