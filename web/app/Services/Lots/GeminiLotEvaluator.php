<?php

namespace App\Services\Lots;

use App\Models\Lot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiLotEvaluator
{
    /**
     * @return array{
     *     risk_score: int,
     *     summary: string,
     *     flags: list<string>,
     *     patio_checks: list<string>,
     *     max_bid_amount: ?float,
     *     estimated_resale: ?float,
     *     estimated_costs: ?float,
     *     target_profit: ?float,
     *     pricing_rationale: ?string
     * }
     */
    public function evaluate(Lot $lot): array
    {
        $apiKey = (string) config('radar.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $model = (string) config('radar.gemini.model', 'gemini-2.5-flash');
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model,
        );

        $parts = [
            ['text' => $this->buildPrompt($lot)],
        ];

        foreach ($this->imageParts($lot) as $imagePart) {
            $parts[] = $imagePart;
        }

        $response = Http::timeout(90)
            ->withQueryParameters(['key' => $apiKey])
            ->post($url, [
                'contents' => [
                    ['parts' => $parts],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.3,
                ],
            ]);

        if (! $response->successful()) {
            $apiMessage = data_get($response->json(), 'error.message');
            Log::warning('Gemini evaluation failed', [
                'lote_id' => $lot->lote_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException($this->friendlyApiError($response->status(), $apiMessage));
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $this->normalizeResult($decoded);
    }

    private function friendlyApiError(int $status, mixed $message): string
    {
        $message = is_string($message) ? trim($message) : '';

        if ($status === 429) {
            return 'Créditos da API Gemini esgotados. Adicione saldo em ai.google.dev.';
        }

        if ($status === 404 && str_contains($message, 'no longer available')) {
            return 'Modelo de IA indisponível para esta conta. Atualize RADAR_GEMINI_MODEL.';
        }

        if ($message !== '') {
            return 'Gemini: '.$message;
        }

        return 'Gemini request failed.';
    }

    private function buildPrompt(Lot $lot): string
    {
        $desconto = $lot->desconto_label ?: ($lot->desconto_pct !== null ? round($lot->desconto_pct * 100, 1).'%' : 'N/A');
        $targetMargin = (float) config('radar.gemini.target_profit_margin_pct', 15);
        $custoEstimado = $lot->custo_estimado_5pct !== null
            ? number_format($lot->custo_estimado_5pct, 2, ',', '.')
            : 'N/A';

        return <<<PROMPT
You are an expert in Brazilian auction vehicles (leilão de salvados). Analyze the photos and lot data. This is NOT a formal inspection — a quick triage for a buyer.

Return JSON only with this shape:
{
  "risk_score": <integer 0-10, where 10 = highest risk of costly surprises>,
  "summary": "<4-6 sentences in Brazilian Portuguese, direct and practical>",
  "flags": ["<short visual or data red flags in Portuguese>", "..."],
  "patio_checks": ["<what to verify in person at the yard>", "..."],
  "pricing": {
    "max_bid": <integer BRL, maximum auction bid to target profit>,
    "fipe_reference": <integer BRL, FIPE value used as reference>,
    "estimated_resale": <integer BRL, realistic post-repair resale price>,
    "estimated_costs": <integer BRL, repairs + transfer/docs + transport + 5% auction fee on max_bid>,
    "target_profit": <integer BRL, desired net profit>,
    "rationale": "<2-4 sentences in Brazilian Portuguese explaining the math and limits>"
  }
}

Rules:
- Be conservative: mention uncertainty when photos are poor or angles missing.
- Cross-check declared damage class (monta/sinistro) vs what you see.
- flags: max 6 items. patio_checks: exactly 3 items.
- Pricing must use FIPE as the main reference table. If fipe_match is not "exact", discount FIPE more aggressively.
- Pricing methodology:
  1) Adjust FIPE downward for monta/sinistro and visible damage to get estimated_resale.
  2) Estimate repair costs from photos (be conservative).
  3) Include fixed costs: ~R$ 2.500 transfer/docs + ~R$ 1.500 transport (adjust if obvious).
  4) Auction fee is 5% on top of max_bid (already inside estimated_costs).
  5) target_profit should be about {$targetMargin}% of estimated_resale.
  6) Solve: max_bid = (estimated_resale - repairs - fixed_costs - target_profit) / 1.05. Round down to nearest R$ 100.
  7) If current bid is already above max_bid, say so in rationale.
  8) All pricing values must be integers in BRL (no decimals).
- Do NOT guarantee profit — this is an estimate for negotiation.

Lot data:
- Title: {$lot->titulo}
- Brand / model / year: {$lot->marca} / {$lot->modelo} / {$lot->ano_mod}
- Current bid: R$ {$lot->lance_atual}
- FIPE: R$ {$lot->fipe_preco} (match: {$lot->fipe_match})
- Discount label: {$desconto}
- Estimated cost at current bid (+5%): R$ {$custoEstimado}
- Damage class: {$lot->classificacao_monta}
- Sinistro: {$lot->sinistro_label} / {$lot->sinistro}
- Yard: {$lot->patio}
- Source: {$lot->fonte}
PROMPT;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function imageParts(Lot $lot): array
    {
        $urls = [];
        if (is_string($lot->foto_capa) && $lot->foto_capa !== '') {
            $urls[] = $lot->foto_capa;
        }
        if (is_array($lot->fotos)) {
            foreach ($lot->fotos as $url) {
                if (is_string($url) && $url !== '' && ! in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        $urls = array_slice($urls, 0, (int) config('radar.gemini.max_images', 4));
        $parts = [];

        foreach ($urls as $url) {
            try {
                $image = Http::timeout(20)->get($url);
                if (! $image->successful()) {
                    continue;
                }
                $mime = $image->header('Content-Type') ?: 'image/jpeg';
                if (! str_starts_with($mime, 'image/')) {
                    continue;
                }
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => explode(';', $mime)[0],
                        'data' => base64_encode($image->body()),
                    ],
                ];
            } catch (\Throwable $e) {
                Log::debug('Skipped lot image for Gemini', [
                    'lote_id' => $lot->lote_id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{
     *     risk_score: int,
     *     summary: string,
     *     flags: list<string>,
     *     patio_checks: list<string>,
     *     max_bid_amount: ?float,
     *     estimated_resale: ?float,
     *     estimated_costs: ?float,
     *     target_profit: ?float,
     *     pricing_rationale: ?string
     * }
     */
    private function normalizeResult(array $decoded): array
    {
        $risk = (int) ($decoded['risk_score'] ?? 5);
        $risk = max(0, min(10, $risk));

        $summary = trim((string) ($decoded['summary'] ?? ''));
        if ($summary === '') {
            $summary = 'Não foi possível gerar um resumo detalhado para este lote.';
        }

        $flags = $this->stringList($decoded['flags'] ?? [], 6);
        $patioChecks = $this->stringList($decoded['patio_checks'] ?? [], 3);

        if ($patioChecks === []) {
            $patioChecks = [
                'Conferir estrutura e alinhamento de painéis no pátio.',
                'Testar funcionamento elétrico e vazamentos visíveis.',
                'Validar documentação e restrições do edital do leilão.',
            ];
        }

        $pricing = is_array($decoded['pricing'] ?? null) ? $decoded['pricing'] : [];
        $maxBid = $this->moneyValue($pricing['max_bid'] ?? null);
        $resale = $this->moneyValue($pricing['estimated_resale'] ?? null);
        $costs = $this->moneyValue($pricing['estimated_costs'] ?? null);
        $profit = $this->moneyValue($pricing['target_profit'] ?? null);
        $rationale = trim((string) ($pricing['rationale'] ?? ''));

        if ($rationale === '' && $maxBid !== null) {
            $rationale = 'Limite calculado com base na FIPE ajustada pelo estado do veículo, custos estimados e margem de lucro alvo.';
        }

        return [
            'risk_score' => $risk,
            'summary' => $summary,
            'flags' => $flags,
            'patio_checks' => $patioChecks,
            'max_bid_amount' => $maxBid,
            'estimated_resale' => $resale,
            'estimated_costs' => $costs,
            'target_profit' => $profit,
            'pricing_rationale' => $rationale !== '' ? $rationale : null,
        ];
    }

    private function moneyValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = preg_replace('/[^\d,.-]/', '', $value) ?? '';
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return $amount > 0 ? round($amount, 2) : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, int $limit): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_slice(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $value,
        ), fn ($item) => $item !== ''), 0, $limit));
    }
}
