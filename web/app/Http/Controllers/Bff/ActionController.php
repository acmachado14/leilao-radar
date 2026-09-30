<?php

namespace App\Http\Controllers\Bff;

use App\Bff\ActionDispatcher;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function store(Request $request, string $name, ActionDispatcher $dispatcher): JsonResponse
    {
        $user = $request->user();
        $payload = $request->all();

        return response()->json($dispatcher->dispatch($name, $request, $user, $payload)->toArray());
    }
}
