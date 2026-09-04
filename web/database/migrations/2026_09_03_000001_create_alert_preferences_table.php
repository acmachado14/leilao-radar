<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('search')->default('');
            $table->json('marcas')->nullable();
            $table->json('fontes')->nullable();
            $table->json('fipe_matches')->nullable();
            $table->json('monta')->nullable();
            $table->decimal('min_desconto', 8, 4)->default(0);
            $table->boolean('exclude_grande')->default(true);
            $table->unsignedInteger('max_days_until')->nullable();
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_whatsapp')->default(false);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_preferences');
    }
};
