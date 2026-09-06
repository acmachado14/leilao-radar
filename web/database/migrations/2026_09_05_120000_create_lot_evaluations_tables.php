<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_evaluations', function (Blueprint $table) {
            $table->string('lote_id')->primary();
            $table->string('status', 16)->default('pending');
            $table->string('source_hash', 64);
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->text('summary')->nullable();
            $table->json('flags')->nullable();
            $table->json('patio_checks')->nullable();
            $table->string('model')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('lot_evaluation_requests', function (Blueprint $table) {
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
        Schema::dropIfExists('lot_evaluation_requests');
        Schema::dropIfExists('lot_evaluations');
    }
};
