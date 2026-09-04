<?php

namespace Tests\Feature;

use App\Livewire\AlertPreferencesForm;
use App\Mail\LotMatchMail;
use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\User;
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
        $user->alertPreferences()->create(AlertPreference::defaults());

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

    public function test_send_test_email_does_not_mark_lots_as_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->alertPreferences()->create(AlertPreference::defaults());
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
