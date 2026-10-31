<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_mails', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->mediumText('body');
            $table->string('from_address')->nullable()->index();
            $table->string('to_address')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('calendar_method')->nullable()->index();
            $table->string('outcome')->nullable()->index();
            $table->text('outcome_detail')->nullable();
            $table->nullableUlidMorphs('resource');
            $table->timestamp('handled_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_mails');
    }
};
