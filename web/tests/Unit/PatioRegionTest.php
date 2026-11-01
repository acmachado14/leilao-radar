<?php

namespace Tests\Unit;

use App\Models\Lot;
use App\Support\PatioRegion;
use Tests\TestCase;

class PatioRegionTest extends TestCase
{
    public function test_parses_yard_addresses_into_the_city_on_the_auction(): void
    {
        $cases = [
            'Br 262 Km375 S/N Zona Rural Juatuba-MG' => ['juatuba', 'Juatuba', 'MG'],
            'Br-262, Km 375   Juatuba-MG' => ['juatuba', 'Juatuba', 'MG'],
            'Juatuba' => ['juatuba', 'Juatuba', null],
            'guarulhos i/sp' => ['guarulhos', 'Guarulhos', 'SP'],
            'guarulhos iii/sp' => ['guarulhos', 'Guarulhos', 'SP'],
            'Via Periferica 4442 Cia Sul Simoes Filho-BA' => ['simoes-filho', 'Simoes Filho', 'BA'],
            'monte mor/sp' => ['monte-mor', 'Monte Mor', 'SP'],
            'Estrada Casa De Pedra - Zona Rural Em Congonhas S/N - Congonhas-MG' => ['congonhas', 'Congonhas', 'MG'],
            'bauru/sp' => ['bauru', 'Bauru', 'SP'],
            'caçapava/sp' => ['cacapava', 'Caçapava', 'SP'],
            'Rod. Dos Inconfidentes SN  Rio Acima-MG' => ['rio-acima', 'Rio Acima', 'MG'],
            'Rodovia Anhanguera 32,5 Guaturinho Cajamar-SP' => ['cajamar', 'Cajamar', 'SP'],
            'curitiba i/pr' => ['curitiba', 'Curitiba', 'PR'],
            'curitiba ii/pr' => ['curitiba', 'Curitiba', 'PR'],
            'sertãozinho/sp' => ['sertaozinho', 'Sertãozinho', 'SP'],
            'Rua Dona Julia 158 Vila Treze De Maio Nova Iguacu-RJ' => ['nova-iguacu', 'Nova Iguacu', 'RJ'],
            'Rua Dos Diamantes 366 Capim Branco 2 Unai-MG' => ['unai', 'Unai', 'MG'],
            'Rua Coronel Fernando Machado 683 Jardim São Paulo Recife-PE' => ['recife', 'Recife', 'PE'],
            'outros locais' => ['outros-locais', 'Outros locais', null],
            'São Paulo-SP' => ['sao-paulo', 'São Paulo', 'SP'],
        ];

        foreach ($cases as $patio => $expected) {
            $parsed = PatioRegion::parse($patio);
            $this->assertNotNull($parsed, $patio);
            $this->assertSame($expected, [$parsed['key'], $parsed['city'], $parsed['uf']], $patio);
        }

        $this->assertNull(PatioRegion::parse(''));
        $this->assertNull(PatioRegion::parse(null));
    }

    public function test_summarize_groups_yards_in_the_same_city_and_keeps_other_places_last(): void
    {
        $lots = collect([
            new Lot(['patio' => 'guarulhos iii/sp']),
            new Lot(['patio' => 'guarulhos i/sp']),
            new Lot(['patio' => 'Juatuba']),
            new Lot(['patio' => 'Br 262 Km375 S/N Zona Rural Juatuba-MG']),
            new Lot(['patio' => 'outros locais']),
        ]);

        $summary = PatioRegion::summarize($lots);

        $this->assertSame([
            ['key' => 'guarulhos', 'label' => 'Guarulhos · SP', 'count' => 2],
            ['key' => 'juatuba', 'label' => 'Juatuba · MG', 'count' => 2],
            ['key' => 'outros-locais', 'label' => 'Outros locais', 'count' => 1],
        ], $summary);
    }
}
