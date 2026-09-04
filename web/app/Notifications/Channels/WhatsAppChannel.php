<?php

namespace App\Notifications\Channels;

use App\Constants\NotificationChannelName;
use App\Models\AlertPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements NotificationChannel
{
    public function name(): string
    {
        return NotificationChannelName::WHATSAPP;
    }

    public function enabledFor(User $user, AlertPreference $preference): bool
    {
        return (bool) config('radar.whatsapp.enabled')
            && $preference->notify_whatsapp
            && filled($user->phone);
    }

    public function send(User $user, Collection $lots): void
    {
        $token = (string) config('radar.whatsapp.token');
        $phoneNumberId = (string) config('radar.whatsapp.phone_number_id');
        $template = (string) config('radar.whatsapp.template');

        if ($token === '' || $phoneNumberId === '') {
            Log::info('WhatsApp channel skipped: missing Cloud API credentials.', [
                'user_id' => $user->id,
                'lots' => $lots->pluck('lote_id')->all(),
            ]);

            return;
        }

        $first = $lots->first();
        Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D+/', '', (string) $user->phone),
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => 'pt_BR'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => (string) ($first?->titulo ?: 'lote')],
                                ['type' => 'text', 'text' => (string) ($first?->desconto_label ?: 'N/A')],
                            ],
                        ],
                    ],
                ],
            ])
            ->throw();
    }
}
