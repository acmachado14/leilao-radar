<?php

namespace App\Jobs;

use App\Constants\LotEvaluationStatus;
use App\Models\Lot;
use App\Models\LotEvaluation;
use App\Services\Lots\GeminiLotEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateLotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public string $loteId) {}

    public function handle(GeminiLotEvaluator $evaluator): void
    {
        $lot = Lot::query()->find($this->loteId);
        if ($lot === null) {
            return;
        }

        $hash = LotEvaluation::sourceHashFor($lot);
        $evaluation = LotEvaluation::query()->find($this->loteId);

        if ($evaluation === null || $evaluation->source_hash !== $hash) {
            return;
        }

        if ($evaluation->status === LotEvaluationStatus::READY) {
            return;
        }

        try {
            $result = $evaluator->evaluate($lot);
            $evaluation->update([
                'status' => LotEvaluationStatus::READY,
                'risk_score' => $result['risk_score'],
                'summary' => $result['summary'],
                'flags' => $result['flags'],
                'patio_checks' => $result['patio_checks'],
                'model' => (string) config('radar.gemini.model', 'gemini-2.5-flash'),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('Lot evaluation job failed', [
                'lote_id' => $this->loteId,
                'error' => $e->getMessage(),
            ]);

            $evaluation->update([
                'status' => LotEvaluationStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
