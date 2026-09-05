<?php

namespace App\Console\Commands;

use App\Services\Alerts\AuctionReminderDispatcher;
use Illuminate\Console\Command;

class DispatchAuctionRemindersCommand extends Command
{
    protected $signature = 'radar:dispatch-auction-reminders';

    protected $description = 'Email subscribers 1 hour before a matching auction, or on the auction day when the clock time is unknown.';

    public function handle(AuctionReminderDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatch();
        $this->info("Reminded {$result['users']} users ({$result['emails']} emails). Skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
