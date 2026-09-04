<?php

namespace App\Services\Lots;

use App\Models\Lot;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LotImporter
{
    /**
     * @return array{count: int, exported_at: ?string}
     */
    public function import(?string $source = null): array
    {
        $payload = $this->loadPayload($source);
        $items = $payload['items'] ?? [];
        $count = 0;

        foreach ($items as $item) {
            $loteId = (string) ($item['lote_id'] ?? '');
            if ($loteId === '') {
                continue;
            }

            Lot::query()->updateOrCreate(
                ['lote_id' => $loteId],
                [
                    'titulo' => $item['titulo'] ?? null,
                    'marca' => $item['marca'] ?? null,
                    'modelo' => $item['modelo'] ?? null,
                    'ano_mod' => $item['ano_mod'] ?? null,
                    'lance_atual' => $item['lance_atual'] ?? null,
                    'fipe_preco' => $item['fipe_preco'] ?? null,
                    'desconto_pct' => $item['desconto_pct'] ?? null,
                    'desconto_label' => $item['desconto_label'] ?? null,
                    'relevance_score' => $item['relevance_score'] ?? null,
                    'leilao_fim' => $item['leilao_fim'] ?? null,
                    'leilao_em' => $item['leilao_em'] ?? null,
                    'fipe_match' => $item['fipe_match'] ?? null,
                    'classificacao_monta' => $item['classificacao_monta'] ?? null,
                    'sinistro' => $item['sinistro'] ?? null,
                    'sinistro_label' => $item['sinistro_label'] ?? null,
                    'patio' => $item['patio'] ?? null,
                    'fonte' => $item['fonte'] ?? 'sodre',
                    'url' => $item['url'] ?? null,
                    'foto_capa' => $item['foto_capa'] ?? null,
                    'fotos' => $item['fotos'] ?? [],
                    'custo_estimado_5pct' => $item['custo_estimado_5pct'] ?? null,
                ],
            );
            $count++;
        }

        $this->mirrorPublicJson($payload);

        return [
            'count' => $count,
            'exported_at' => $payload['exported_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadPayload(?string $source = null): array
    {
        $path = $source ?: config('radar.lots_path');
        if (is_string($path) && $path !== '') {
            if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:\\\\/', $path)) {
                $path = base_path($path);
            }
            if (is_file($path)) {
                $decoded = json_decode((string) file_get_contents($path), true);
                if (! is_array($decoded)) {
                    throw new RuntimeException('Invalid lots JSON at '.$path);
                }

                return $decoded;
            }
        }

        $url = $source ?: (string) config('radar.lots_url');
        $response = Http::timeout(60)->acceptJson()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch lots JSON: HTTP '.$response->status());
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid lots JSON from '.$url);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mirrorPublicJson(array $payload): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $target = public_path('data/lotes.json');
        $dir = dirname($target);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (is_link($target) || (is_file($target) && ! is_writable($target))) {
            unlink($target);
        }

        file_put_contents($target, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
