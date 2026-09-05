<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LotInterestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'lote_ids' => $request->user()->lotInterests()->pluck('lote_id')->values(),
        ]);
    }

    public function store(Request $request, string $lote): JsonResponse
    {
        $lot = Lot::query()->find($lote);
        if ($lot === null) {
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        $request->user()->lotInterests()->firstOrCreate(['lote_id' => $lot->lote_id]);

        return response()->json([
            'interested' => true,
            'lote_id' => $lot->lote_id,
        ]);
    }

    public function destroy(Request $request, string $lote): JsonResponse
    {
        $request->user()->lotInterests()->where('lote_id', $lote)->delete();

        return response()->json([
            'interested' => false,
            'lote_id' => $lote,
        ]);
    }
}
