<?php

namespace App\Console\Commands;

use App\Services\Alerts\AuctionReminderDispatcher;
use Illuminate\Console\Command;

class DispatchAuctionRemindersCommand extends Command
{
    protected $signature = 'radar:dispatch-auction-reminders';

    protected $description = 'Email 1 hour before auction start (or on the day if no clock time) only for lots the user marked as interested.';

    public function handle(AuctionReminderDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatch();
        $this->info("Reminded {$result['users']} users ({$result['emails']} emails). Skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
