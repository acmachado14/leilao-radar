<?php

namespace App\Http\Controllers;

use App\Constants\LotEvaluationStatus;
use App\Jobs\EvaluateLotJob;
use App\Models\Lot;
use App\Models\LotEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LotEvaluationController extends Controller
{
    public function show(Request $request, string $lote): JsonResponse
    {
        if (! $this->userHasRequested($request, $lote)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($this->buildResponse($lote));
    }

    public function store(Request $request, string $lote): JsonResponse
    {
        $lot = Lot::query()->find($lote);
        if ($lot === null) {
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        $request->user()->lotEvaluationRequests()->firstOrCreate([
            'lote_id' => $lot->lote_id,
        ]);

        $hash = LotEvaluation::sourceHashFor($lot);
        $evaluation = LotEvaluation::query()->find($lot->lote_id);

        if ($evaluation !== null && $evaluation->source_hash === $hash && $evaluation->status === LotEvaluationStatus::READY) {
            return response()->json($this->buildResponse($lot->lote_id));
        }

        if ($evaluation !== null && $evaluation->source_hash === $hash && $evaluation->status === LotEvaluationStatus::PENDING) {
            return response()->json($this->buildResponse($lot->lote_id));
        }

        LotEvaluation::query()->updateOrCreate(
            ['lote_id' => $lot->lote_id],
            [
                'status' => LotEvaluationStatus::PENDING,
                'source_hash' => $hash,
                'risk_score' => null,
                'summary' => null,
                'flags' => null,
                'patio_checks' => null,
                'model' => null,
                'error_message' => null,
            ],
        );

        EvaluateLotJob::dispatch($lot->lote_id);

        return response()->json($this->buildResponse($lot->lote_id));
    }

    private function userHasRequested(Request $request, string $lote): bool
    {
        return $request->user()
            ->lotEvaluationRequests()
            ->where('lote_id', $lote)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(string $loteId): array
    {
        $evaluation = LotEvaluation::query()->find($loteId);

        if ($evaluation === null) {
            return [
                'status' => LotEvaluationStatus::PENDING,
                'evaluation' => null,
            ];
        }

        return [
            'status' => $evaluation->status,
            'evaluation' => $evaluation->isReady() ? $evaluation->toPublicArray() : null,
            'error' => $evaluation->status === LotEvaluationStatus::FAILED
                ? ($evaluation->error_message ?: 'Falha ao gerar avaliação.')
                : null,
        ];
    }
}
