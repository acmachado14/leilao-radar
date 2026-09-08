<?php

namespace Tests\Feature;

use App\Livewire\AlertPreferencesForm;
use App\Mail\LotMatchMail;
use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\User;
use App\Support\SearchYearExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AlertPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_more_than_one_preference(): void
    {
        $user = User::factory()->create();
        $user->alertPreferences()->create(AlertPreference::scoped(['name' => 'Um']));

        Livewire::actingAs($user)
            ->test(AlertPreferencesForm::class)
            ->call('createNew')
            ->set('name', 'Amarok')
            ->set('search', 'Amarok')
            ->set('fipe_matches', ['exact', 'closest', 'failed'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, $user->alertPreferences()->count());
        $this->assertTrue($user->alertPreferences()->where('search', 'Amarok')->exists());
    }

    public function test_saves_year_range_and_extracts_year_from_search(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AlertPreferencesForm::class)
            ->call('createNew')
            ->set('name', 'Jetta')
            ->set('search', 'Jetta GLI 2018')
            ->set('fipe_matches', ['exact', 'closest', 'failed'])
            ->call('save')
            ->assertHasNoErrors();

        $preference = $user->alertPreferences()->first();
        $this->assertSame('Jetta GLI', $preference->search);
        $this->assertSame(2018, $preference->ano_min);
        $this->assertSame(2018, $preference->ano_max);
    }

    public function test_backfill_moves_year_from_existing_search_into_year_fields(): void
    {
        $user = User::factory()->create();
        $preference = $user->alertPreferences()->create([
            ...AlertPreference::defaults(),
            'search' => 'Amarok 2018/2019',
        ]);

        $extracted = SearchYearExtractor::extract((string) $preference->search);
        $preference->update([
            'search' => $extracted['search'],
            'ano_min' => $extracted['ano_min'],
            'ano_max' => $extracted['ano_max'],
        ]);

        $preference->refresh();
        $this->assertSame('Amarok', $preference->search);
        $this->assertSame(2018, $preference->ano_min);
        $this->assertSame(2019, $preference->ano_max);
    }

    public function test_trial_plan_blocks_extra_alert_preferences(): void
    {
        $user = User::factory()->create(['plan' => 'trial']);
        $user->alertPreferences()->create(AlertPreference::scoped(['name' => 'Um', 'search' => 'Amarok']));
        $user->alertPreferences()->create([...AlertPreference::defaults(), 'name' => 'Dois', 'search' => 'Jetta']);

        Livewire::actingAs($user)
            ->test(AlertPreferencesForm::class)
            ->call('createNew')
            ->set('name', 'Tres')
            ->set('search', 'Civic')
            ->set('fipe_matches', ['exact', 'closest', 'failed'])
            ->call('save')
            ->assertHasErrors(['search']);

        $this->assertSame(2, $user->alertPreferences()->count());
    }

    public function test_rejects_empty_search(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AlertPreferencesForm::class)
            ->call('createNew')
            ->set('name', 'Tudo')
            ->set('search', '')
            ->set('fipe_matches', ['exact', 'closest', 'failed'])
            ->call('save')
            ->assertHasErrors(['search']);

        $this->assertSame(0, $user->alertPreferences()->count());
    }

    public function test_send_test_email_does_not_mark_lots_as_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->alertPreferences()->create(AlertPreference::scoped());
        Lot::factory()->create(['lote_id' => 'preview-1']);

        $this->artisan('radar:send-test-email', ['email' => 'ana@example.com'])
            ->assertSuccessful();

        Mail::assertSent(LotMatchMail::class, 1);
        $this->assertDatabaseMissing('lot_alert_sends', [
            'user_id' => $user->id,
            'lote_id' => 'preview-1',
        ]);
    }
}
