<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\RevenueCatWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueCatWebhookController extends Controller
{
    public function store(Request $request, RevenueCatWebhookProcessor $processor): JsonResponse
    {
        $expected = (string) config('radar.revenuecat.webhook_auth');
        $given = (string) $request->header('Authorization', '');
        $given = preg_replace('/^Bearer\s+/i', '', $given) ?? $given;

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $processor->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
