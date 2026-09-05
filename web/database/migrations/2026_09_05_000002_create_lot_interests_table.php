<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('lote_id');
            $table->timestamps();

            $table->unique(['user_id', 'lote_id']);
            $table->index('lote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_interests');
    }
};
