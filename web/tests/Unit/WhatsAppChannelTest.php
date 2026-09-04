<?php

namespace Tests\Unit;

use App\Models\AlertPreference;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_stays_disabled_until_cloud_api_flag_is_on(): void
    {
        $user = User::factory()->create(['phone' => '11999999999']);
        $preference = new AlertPreference(array_merge(AlertPreference::defaults(), [
            'notify_whatsapp' => true,
        ]));

        $channel = new WhatsAppChannel;
        $this->assertFalse($channel->enabledFor($user, $preference));

        config(['radar.whatsapp.enabled' => true]);
        $this->assertTrue($channel->enabledFor($user, $preference));
    }
}
