<?php

namespace App\Console\Commands;

use App\Services\Alerts\AlertDispatcher;
use App\Services\Lots\LotImporter;
use Illuminate\Console\Command;

class DispatchAlertsCommand extends Command
{
    protected $signature = 'radar:dispatch-alerts {--skip-sync : Do not refresh lots before matching} {--source= : Optional lots JSON path or URL}';

    protected $description = 'Match subscriber preferences and queue digest emails.';

    public function handle(LotImporter $importer, AlertDispatcher $dispatcher): int
    {
        if (! $this->option('skip-sync')) {
            $source = $this->option('source');
            $imported = $importer->import(is_string($source) && $source !== '' ? $source : null);
            $this->info("Synced {$imported['count']} lots.");
        }

        $result = $dispatcher->dispatch();
        $this->info("Notified {$result['users']} users ({$result['emails']} emails). Skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
