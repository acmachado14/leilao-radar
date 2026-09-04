<?php

namespace Database\Factories;

use App\Models\Lot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lote_id' => (string) fake()->unique()->numerify('2######'),
            'titulo' => 'Toyota Corolla Xei',
            'marca' => 'Toyota',
            'modelo' => 'Corolla Xei',
            'ano_mod' => 2018,
            'lance_atual' => 20000,
            'fipe_preco' => 80000,
            'desconto_pct' => 0.75,
            'desconto_label' => '75.0%',
            'relevance_score' => 0.9,
            'leilao_fim' => now('America/Sao_Paulo')->addDays(3)->format('Y-m-d H:i:s'),
            'leilao_em' => now('America/Sao_Paulo')->addDays(3)->format('Y-m-d H:i:s'),
            'fipe_match' => 'exact',
            'classificacao_monta' => 'pequena',
            'fonte' => 'sodre',
            'url' => 'https://example.test/lote',
        ];
    }
}
