<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_alert_sends', function (Blueprint $table) {
            $table->string('kind', 32)->default('match')->after('channel');
        });

        Schema::table('lot_alert_sends', function (Blueprint $table) {
            $table->dropUnique('lot_alert_sends_user_id_lote_id_channel_unique');
            $table->unique(['user_id', 'lote_id', 'channel', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('lot_alert_sends', function (Blueprint $table) {
            $table->dropUnique('lot_alert_sends_user_id_lote_id_channel_kind_unique');
        });

        Schema::table('lot_alert_sends', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->unique(['user_id', 'lote_id', 'channel']);
        });
    }
};
