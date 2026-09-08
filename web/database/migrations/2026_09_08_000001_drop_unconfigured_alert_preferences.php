<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('alert_preferences')->cursor() as $preference) {
            if (trim((string) $preference->search) !== '') {
                continue;
            }

            $marcas = $preference->marcas;
            if (is_string($marcas)) {
                $marcas = json_decode($marcas, true) ?: [];
            }
            if (! is_array($marcas)) {
                $marcas = [];
            }
            if (array_values(array_filter($marcas)) !== []) {
                continue;
            }

            if (! (bool) $preference->notify_email) {
                continue;
            }

            DB::table('alert_preferences')->where('id', $preference->id)->delete();
        }
    }

    public function down(): void
    {
        // Catch-all recortes are not restored: they caused unsolicited digest e-mails.
    }
};
