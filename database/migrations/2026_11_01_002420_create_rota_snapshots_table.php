<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rota_snapshots', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->json('entries');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rota_snapshots');
    }
};
