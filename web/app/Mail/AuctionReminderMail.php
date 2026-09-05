<?php

namespace App\Mail;

use App\Models\Lot;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class AuctionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Lot>  $lots
     */
    public function __construct(
        public User $user,
        public Collection $lots,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->lots->count();
        $first = $this->lots->first();
        $hasClock = $this->lots->contains(fn (Lot $lot) => $lot->hasScheduledClockTime());

        if ($count === 1) {
            $prefix = $hasClock ? 'Leilão em 1 hora' : 'Leilão hoje';
            $subject = $prefix.': '.($first?->titulo ?? 'lote no VerifyRadar');
        } else {
            $subject = $hasClock
                ? "{$count} leilões em cerca de 1 hora no VerifyRadar"
                : "{$count} leilões hoje no VerifyRadar";
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auction-reminder',
            with: [
                'user' => $this->user,
                'lots' => $this->lots,
                'catalogUrl' => url('/'),
                'alertsUrl' => route('alertas'),
                'unsubscribeUrl' => URL::signedRoute('alertas.unsubscribe', ['user' => $this->user->id]),
            ],
        );
    }
}
