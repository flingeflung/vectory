<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Konten entstehen nur über den Admin und den Aktivierungslink, nie durch Selbst-Registrierung (Ralf, 2026-10-08). */
class NoSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_page_and_endpoint_do_not_exist(): void
    {
        // Die Adresse gibt es nicht mehr; Gäste landen bei der Anmeldung (Fallback), nicht auf einer Registrierungsseite
        $this->assertNotSame(200, $this->get('/register')->getStatusCode());
        $this->assertNotSame(200, $this->post('/register', ['name' => 'Eindringling', 'email' => 'x@example.test', 'password' => 'abcd', 'password_confirmation' => 'abcd'])->getStatusCode());
        $this->assertDatabaseMissing('users', ['email' => 'x@example.test']);
    }
}
