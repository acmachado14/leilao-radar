<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lot_alert_sends', 'kind')) {
            Schema::table('lot_alert_sends', function (Blueprint $table) {
                $table->string('kind', 32)->default('match')->after('channel');
            });
        }

        $indexNames = collect(Schema::getIndexes('lot_alert_sends'))->pluck('name');
        $needsUniqueSwap = $indexNames->contains('lot_alert_sends_user_id_lote_id_channel_unique');
        $missingKindUnique = ! $indexNames->contains('lot_alert_sends_user_id_lote_id_channel_kind_unique');

        if (! $needsUniqueSwap && ! $missingKindUnique) {
            return;
        }

        Schema::table('lot_alert_sends', function (Blueprint $table) use ($needsUniqueSwap, $missingKindUnique) {
            // MySQL can use the old unique as the supporting index for user_id FK.
            $table->dropForeign(['user_id']);

            if ($needsUniqueSwap) {
                $table->dropUnique('lot_alert_sends_user_id_lote_id_channel_unique');
            }

            if ($missingKindUnique) {
                $table->unique(['user_id', 'lote_id', 'channel', 'kind']);
            }

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $indexNames = collect(Schema::getIndexes('lot_alert_sends'))->pluck('name');

        Schema::table('lot_alert_sends', function (Blueprint $table) use ($indexNames) {
            $table->dropForeign(['user_id']);

            if ($indexNames->contains('lot_alert_sends_user_id_lote_id_channel_kind_unique')) {
                $table->dropUnique('lot_alert_sends_user_id_lote_id_channel_kind_unique');
            }

            $table->unique(['user_id', 'lote_id', 'channel']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        if (Schema::hasColumn('lot_alert_sends', 'kind')) {
            Schema::table('lot_alert_sends', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
