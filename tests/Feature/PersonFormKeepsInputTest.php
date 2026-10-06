<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonFormKeepsInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_save_keeps_the_entered_values_in_the_form(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);
        Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'first_name' => 'Erna', 'last_name' => 'Erste', 'short_name' => 'EE', 'active' => true]);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'first_name' => '', 'last_name' => 'Neue Person', 'active' => true]);

        $payload = [
            'first_name' => 'Gustav',
            'last_name' => 'Gründlich',
            'short_name' => 'EE', // schon vergeben
            'email' => 'gustav@example.test',
            'remarks' => 'Bitte nicht verlieren',
        ];

        // Overlay (Normalfall) und eigene Seite verhalten sich gleich
        foreach ([['X-Overlay' => '1'], []] as $headers) {
            $response = $this->actingAs($admin)->withHeaders($headers)->post(route('admin.personen.update', $person), $payload);
            $response->assertStatus(422)
                ->assertSee('value="Gustav"', false)
                ->assertSee('value="Gründlich"', false)
                ->assertSee('value="gustav@example.test"', false)
                ->assertSee('Bitte nicht verlieren');
        }

        // Es wurde nichts gespeichert
        $this->assertSame('Neue Person', $person->fresh()->last_name);
    }

    public function test_fields_are_checked_on_blur_without_saving(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);
        Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'first_name' => 'Erna', 'last_name' => 'Erste', 'short_name' => 'EE', 'active' => true]);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'first_name' => '', 'last_name' => 'Neue Person', 'active' => true]);
        $this->actingAs($admin);
        $check = fn (string $field, string $value) => $this->getJson(route('admin.personen.feldpruefung', ['person' => $person, 'field' => $field, 'value' => $value]))->assertOk()->json('message');

        $this->assertNotNull($check('short_name', 'EE'));   // schon vergeben
        $this->assertNotNull($check('short_name', 'X'));    // zu kurz
        $this->assertNull($check('short_name', 'NP'));      // frei
        $this->assertNull($check('short_name', ''));        // leer ist erlaubt
        $this->assertNotNull($check('email', 'kein-mail')); // ungültig
        $this->assertNull($check('email', 'a@b.de'));
        $this->getJson(route('admin.personen.feldpruefung', ['person' => $person, 'field' => 'last_name', 'value' => 'x']))->assertStatus(422);
        $this->assertNull($person->fresh()->short_name);
    }
}
