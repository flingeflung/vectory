<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Gäste werden von der Startseite zum Login weitergeleitet (siehe
     * routes/web.php) - kein 200, wie es der unangepasste Breeze-
     * Beispieltest ursprünglich erwartete.
     */
    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
