<?php

namespace App\Console\Commands;

use App\Mail\LotMatchMail;
use App\Models\Lot;
use App\Models\User;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmailCommand extends Command
{
    protected $signature = 'radar:send-test-email {email : Recipient account e-mail} {--limit=5 : How many lots to include}';

    protected $description = 'Send a sample digest e-mail without marking lots as already notified.';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("No user found for {$email}. Create the account first.");

            return self::FAILURE;
        }

        $limit = max(1, min(8, (int) $this->option('limit')));
        $lots = $dispatcher->previewForUser($user, $limit);

        if ($lots->isEmpty()) {
            $lots = Lot::query()
                ->get()
                ->filter(fn (Lot $lot) => $lot->isUpcoming())
                ->sortByDesc(fn (Lot $lot) => $lot->relevance_score ?? 0)
                ->take($limit)
                ->values();
        }

        if ($lots->isEmpty()) {
            $this->error('No lots in the snapshot to preview.');

            return self::FAILURE;
        }

        Mail::to($user->email)->sendNow(new LotMatchMail($user, $lots, isTest: true));
        $this->info("Sent test digest with {$lots->count()} lots to {$user->email}.");

        return self::SUCCESS;
    }
}
