<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No foreign keys: the record must survive deletion of either account.
        Schema::create('personal_data_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->ulid('viewer_id');
            $table->string('viewer_name');
            $table->ulid('subject_id');
            $table->string('subject_name');
            $table->string('section');
            $table->timestamp('acknowledged_at');

            $table->index(['viewer_id', 'subject_id', 'section', 'acknowledged_at'], 'personal_data_ack_lookup');
            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_data_acknowledgements');
    }
};
