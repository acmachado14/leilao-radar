<?php

namespace App\Http\Controllers;

use App\Constants\LotEvaluationStatus;
use App\Jobs\EvaluateLotJob;
use App\Models\Lot;
use App\Models\LotEvaluation;
use App\Services\Billing\PlanQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LotEvaluationController extends Controller
{
    public function __construct(private PlanQuota $quota) {}

    public function show(Request $request, string $lote): JsonResponse
    {
        if (! $this->userHasRequested($request, $lote)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($this->buildResponse($request, $lote));
    }

    public function store(Request $request, string $lote): JsonResponse
    {
        $lot = Lot::query()->find($lote);
        if ($lot === null) {
            return response()->json(['message' => 'Lote não encontrado.'], 404);
        }

        $user = $request->user();
        if (! $this->quota->canConsult($user, $lot->lote_id)) {
            $snapshot = $this->quota->snapshot($user);

            return response()->json([
                'status' => 'quota_exceeded',
                'error' => 'Você usou as análises de IA deste mês. Fale com um atendente para subir de plano.',
                'quota' => $snapshot,
            ], 402);
        }

        $alreadyRequested = $this->userHasRequested($request, $lot->lote_id);
        $user->lotEvaluationRequests()->firstOrCreate([
            'lote_id' => $lot->lote_id,
        ]);

        $hash = LotEvaluation::sourceHashFor($lot);
        $evaluation = LotEvaluation::query()->find($lot->lote_id);

        if ($evaluation !== null && $evaluation->source_hash === $hash && $evaluation->status === LotEvaluationStatus::READY) {
            if (! $alreadyRequested || ! $this->quota->alreadyBilledThisPeriod($user, $lot->lote_id)) {
                $this->quota->record($user, $lot->lote_id, 'cache', false);
            }

            return response()->json($this->buildResponse($request, $lot->lote_id));
        }

        if ($evaluation !== null && $evaluation->source_hash === $hash && $evaluation->status === LotEvaluationStatus::PENDING) {
            if (! $alreadyRequested || ! $this->quota->alreadyBilledThisPeriod($user, $lot->lote_id)) {
                $this->quota->record($user, $lot->lote_id, 'api', true);
            }

            return response()->json($this->buildResponse($request, $lot->lote_id));
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
                'max_bid_amount' => null,
                'estimated_resale' => null,
                'estimated_costs' => null,
                'target_profit' => null,
                'pricing_rationale' => null,
                'model' => null,
                'error_message' => null,
            ],
        );

        $this->quota->record($user, $lot->lote_id, 'api', true);
        EvaluateLotJob::dispatch($lot->lote_id, $user->id);

        return response()->json($this->buildResponse($request, $lot->lote_id));
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
    private function buildResponse(Request $request, string $loteId): array
    {
        $evaluation = LotEvaluation::query()->find($loteId);
        $payload = [
            'quota' => $this->quota->snapshot($request->user()),
        ];

        if ($evaluation === null) {
            return [
                ...$payload,
                'status' => LotEvaluationStatus::PENDING,
                'evaluation' => null,
            ];
        }

        return [
            ...$payload,
            'status' => $evaluation->status,
            'evaluation' => $evaluation->isReady() ? $evaluation->toPublicArray() : null,
            'error' => $evaluation->status === LotEvaluationStatus::FAILED
                ? ($evaluation->error_message ?: 'Falha ao gerar avaliação.')
                : null,
        ];
    }
}
