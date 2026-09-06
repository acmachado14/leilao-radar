<?php

namespace Tests\Feature;

use App\Mail\AuctionReminderMail;
use App\Mail\LotMatchMail;
use App\Models\Lot;
use App\Models\User;
use App\Support\EmailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_match_email_includes_branding_search_icon_and_car_photo(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create([
            'foto_capa' => 'https://example.test/car-cover.jpg',
            'titulo' => 'Toyota Corolla Xei',
        ]);

        $html = (new LotMatchMail($user, collect([$lot])))->render();

        $this->assertStringContainsString(EmailBranding::logoUrl(), $html);
        $this->assertStringContainsString(EmailBranding::searchIconUrl(), $html);
        $this->assertStringContainsString('https://example.test/car-cover.jpg', $html);
        $this->assertStringContainsString('Toyota Corolla Xei', $html);
    }

    public function test_auction_reminder_email_includes_branding_and_lot_cards(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create([
            'foto_capa' => 'https://example.test/reminder.jpg',
            'titulo' => 'Honda Civic EX',
        ]);

        $html = (new AuctionReminderMail($user, collect([$lot])))->render();

        $this->assertStringContainsString(EmailBranding::logoUrl(), $html);
        $this->assertStringContainsString(EmailBranding::searchIconUrl(), $html);
        $this->assertStringContainsString('https://example.test/reminder.jpg', $html);
        $this->assertStringContainsString('Honda Civic EX', $html);
    }

    public function test_lot_without_photo_falls_back_to_radar_logo(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create([
            'foto_capa' => null,
            'fotos' => null,
        ]);

        $html = (new LotMatchMail($user, collect([$lot])))->render();

        $this->assertSame(EmailBranding::logoUrl(), $lot->emailPhotoUrl());
        $this->assertStringContainsString(EmailBranding::logoUrl(), $html);
    }
}
