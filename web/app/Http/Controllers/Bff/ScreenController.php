<?php

namespace App\Http\Controllers\Bff;

use App\Bff\ScreenComposer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreenController extends Controller
{
    public function show(Request $request, string $name, ScreenComposer $composer): JsonResponse
    {
        $user = $request->user();
        $params = $request->query();

        return response()->json($composer->compose($name, $request, $user, $params)->toArray());
    }
}
