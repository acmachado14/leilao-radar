<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_terms_and_privacy_are_public(): void
    {
        $this->get('/termos')
            ->assertOk()
            ->assertSee('Termos de uso')
            ->assertSee('alucina')
            ->assertDontSee('&lt;article', false);
        $this->get('/privacidade')
            ->assertOk()
            ->assertSee('Privacidade')
            ->assertSee('Tratamos nome, e-mail')
            ->assertDontSee('&lt;article', false);
    }
}
