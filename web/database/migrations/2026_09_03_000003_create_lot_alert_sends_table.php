<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_alert_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('lote_id');
            $table->string('channel');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'lote_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_alert_sends');
    }
};
