<?php

namespace App\Services\Push;

use App\Models\DevicePushToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushClient
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  Collection<int, string>  $expoPushTokens
     * @param  array<string, mixed>  $data
     */
    public function sendToTokens(Collection $expoPushTokens, string $title, string $body, array $data = []): void
    {
        $tokens = $expoPushTokens->filter(fn (string $token) => $token !== '')->unique()->values();
        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn (string $token) => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'data' => $data,
        ])->all();

        $response = Http::timeout(15)
            ->acceptJson()
            ->post(self::ENDPOINT, $messages);

        if (! $response->successful()) {
            Log::warning('Expo push request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $this->pruneInvalidTokens($tokens, $response->json('data') ?? []);
    }

    /**
     * @param  Collection<int, string>  $tokens
     * @param  list<array<string, mixed>>  $results
     */
    private function pruneInvalidTokens(Collection $tokens, array $results): void
    {
        foreach ($results as $index => $result) {
            if (! is_array($result)) {
                continue;
            }
            $status = (string) ($result['status'] ?? '');
            if ($status !== 'error') {
                continue;
            }
            $details = $result['details'] ?? [];
            $error = is_array($details) ? (string) ($details['error'] ?? '') : '';
            if ($error !== 'DeviceNotRegistered') {
                continue;
            }
            $token = $tokens->get($index);
            if (is_string($token) && $token !== '') {
                DevicePushToken::query()->where('token', $token)->delete();
            }
        }
    }
}
