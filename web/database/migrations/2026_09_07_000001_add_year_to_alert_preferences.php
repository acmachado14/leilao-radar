<?php

use App\Support\SearchYearExtractor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_preferences', function (Blueprint $table) {
            $table->unsignedSmallInteger('ano_min')->nullable()->after('search');
            $table->unsignedSmallInteger('ano_max')->nullable()->after('ano_min');
        });

        foreach (DB::table('alert_preferences')->cursor() as $preference) {
            $extracted = SearchYearExtractor::extract((string) $preference->search);
            if ($extracted['ano_min'] === null) {
                continue;
            }

            DB::table('alert_preferences')
                ->where('id', $preference->id)
                ->update([
                    'search' => $extracted['search'],
                    'ano_min' => $extracted['ano_min'],
                    'ano_max' => $extracted['ano_max'],
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('alert_preferences', function (Blueprint $table) {
            $table->dropColumn(['ano_min', 'ano_max']);
        });
    }
};
