<?php

namespace Tests\Feature;

use App\Constants\LotEvaluationStatus;
use App\Jobs\EvaluateLotJob;
use App\Models\Lot;
use App\Models\LotEvaluation;
use App\Models\User;
use App\Services\Lots\GeminiLotEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LotEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_request_evaluation(): void
    {
        Lot::factory()->create(['lote_id' => 'guest-1']);

        $this->postJson(route('avaliacoes.store', ['lote' => 'guest-1']))
            ->assertUnauthorized();
    }

    public function test_pending_user_cannot_request_evaluation(): void
    {
        $user = User::factory()->pending()->create();
        Lot::factory()->create(['lote_id' => 'pending-1']);

        $this->actingAs($user)
            ->postJson(route('avaliacoes.store', ['lote' => 'pending-1']))
            ->assertRedirect(route('aguardando'));
    }

    public function test_approved_user_can_request_evaluation_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $lot = Lot::factory()->create(['lote_id' => 'eval-1']);

        $this->actingAs($user)
            ->postJson(route('avaliacoes.store', ['lote' => $lot->lote_id]))
            ->assertOk()
            ->assertJson(['status' => LotEvaluationStatus::PENDING]);

        $this->assertDatabaseHas('lot_evaluation_requests', [
            'user_id' => $user->id,
            'lote_id' => 'eval-1',
        ]);

        Queue::assertPushed(EvaluateLotJob::class, fn (EvaluateLotJob $job) => $job->loteId === 'eval-1');
    }

    public function test_user_without_request_cannot_view_evaluation(): void
    {
        $user = User::factory()->create();
        Lot::factory()->create(['lote_id' => 'hidden-1']);
        LotEvaluation::query()->create([
            'lote_id' => 'hidden-1',
            'status' => LotEvaluationStatus::READY,
            'source_hash' => 'abc',
            'risk_score' => 4,
            'summary' => 'ok',
            'flags' => [],
            'patio_checks' => [],
            'model' => 'gemini-2.5-flash',
        ]);

        $this->actingAs($user)
            ->getJson(route('avaliacoes.show', ['lote' => 'hidden-1']))
            ->assertNotFound();
    }

    public function test_second_user_reuses_ready_cache_without_new_job(): void
    {
        Queue::fake();

        $lot = Lot::factory()->create(['lote_id' => 'shared-1']);
        LotEvaluation::query()->create([
            'lote_id' => 'shared-1',
            'status' => LotEvaluationStatus::READY,
            'source_hash' => LotEvaluation::sourceHashFor($lot),
            'risk_score' => 3,
            'summary' => 'Parecer cacheado.',
            'flags' => ['Pintura irregular'],
            'patio_checks' => ['Motor', 'Estrutura', 'Documentos'],
            'model' => 'gemini-2.5-flash',
        ]);

        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)
            ->postJson(route('avaliacoes.store', ['lote' => 'shared-1']))
            ->assertOk()
            ->assertJsonPath('evaluation.summary', 'Parecer cacheado.');

        $this->actingAs($second)
            ->postJson(route('avaliacoes.store', ['lote' => 'shared-1']))
            ->assertOk()
            ->assertJsonPath('evaluation.summary', 'Parecer cacheado.');

        Queue::assertNothingPushed();
    }

    public function test_job_marks_evaluation_ready_using_gemini_response(): void
    {
        config([
            'radar.gemini.api_key' => 'test-key',
            'radar.gemini.model' => 'gemini-2.5-flash',
        ]);

        $lot = Lot::factory()->create([
            'lote_id' => 'job-1',
            'foto_capa' => 'https://example.test/car.jpg',
        ]);

        $evaluation = LotEvaluation::query()->create([
            'lote_id' => 'job-1',
            'status' => LotEvaluationStatus::PENDING,
            'source_hash' => LotEvaluation::sourceHashFor($lot),
        ]);

        Http::fake([
            'https://example.test/car.jpg' => Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'risk_score' => 6,
                                'summary' => 'Carro com sinais de batida lateral.',
                                'flags' => ['Amassado na porta'],
                                'patio_checks' => ['Longarina', 'Pintura', 'FIPE no edital'],
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ]],
            ]),
        ]);

        (new EvaluateLotJob('job-1'))->handle(app(GeminiLotEvaluator::class));

        $evaluation->refresh();
        $this->assertSame(LotEvaluationStatus::READY, $evaluation->status);
        $this->assertSame(6, $evaluation->risk_score);
        $this->assertSame('Carro com sinais de batida lateral.', $evaluation->summary);
    }
}
