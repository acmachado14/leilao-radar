<?php

namespace App\Console\Commands;

use App\Services\Lots\LotImporter;
use Illuminate\Console\Command;

class SyncLotsCommand extends Command
{
    protected $signature = 'radar:sync-lots {--source=}';

    protected $description = 'Import auction lots from the public JSON snapshot.';

    public function handle(LotImporter $importer): int
    {
        $source = $this->option('source');
        $result = $importer->import(is_string($source) && $source !== '' ? $source : null);
        $this->info("Imported {$result['count']} lots (exported_at: ".($result['exported_at'] ?? 'n/a').').');

        return self::SUCCESS;
    }
}
