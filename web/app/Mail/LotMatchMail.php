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

class LotMatchMail extends Mailable implements ShouldQueue
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

        return new Envelope(
            subject: $count === 1
                ? 'Novo lote no Radar: '.$this->lots->first()?->titulo
                : "{$count} lotes novos no Leilão Radar",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.lot-match',
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
