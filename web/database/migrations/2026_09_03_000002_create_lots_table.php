<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->string('lote_id')->primary();
            $table->string('titulo')->nullable();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->unsignedSmallInteger('ano_mod')->nullable();
            $table->decimal('lance_atual', 12, 2)->nullable();
            $table->decimal('fipe_preco', 12, 2)->nullable();
            $table->decimal('desconto_pct', 10, 4)->nullable();
            $table->string('desconto_label')->nullable();
            $table->decimal('relevance_score', 8, 4)->nullable();
            $table->string('leilao_fim')->nullable();
            $table->string('leilao_em')->nullable();
            $table->string('fipe_match')->nullable();
            $table->string('classificacao_monta')->nullable();
            $table->string('sinistro')->nullable();
            $table->string('sinistro_label')->nullable();
            $table->string('patio')->nullable();
            $table->string('fonte')->nullable();
            $table->string('url')->nullable();
            $table->string('foto_capa')->nullable();
            $table->json('fotos')->nullable();
            $table->decimal('custo_estimado_5pct', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
