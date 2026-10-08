<?php

namespace Tests\Feature\Auth;

use App\Mail\AccountActivationMail;
use App\Models\AccountActivationToken;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Konto-Aktivierung per Link, Sperre für deaktivierte Personen, Passwort-Regeln (Ralf, 2026-10-08). */
class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
    }

    private function person(string $email = 'neu@example.test', bool $active = true): Person
    {
        return Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id, 'first_name' => 'Nora', 'last_name' => 'Neu', 'email' => $email, 'active' => $active,
        ]);
    }

    /** @return array{0: User, 1: string} vorbereitetes Konto und der Klartext-Token aus der Mail */
    private function preparedAccount(string $email = 'neu@example.test'): array
    {
        Mail::fake();
        $person = $this->person($email);
        $this->actingAs($this->admin)->post(route('admin.personen.account.prepare', $person), ['email' => $email])->assertRedirect();
        $token = null;
        Mail::assertSent(AccountActivationMail::class, function (AccountActivationMail $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });
        auth()->logout();

        return [User::query()->where('email', $email)->firstOrFail(), $token];
    }

    public function test_admin_prepares_an_account_and_the_person_activates_it(): void
    {
        [$user, $token] = $this->preparedAccount();

        $this->assertTrue($user->isPending());
        $this->assertNull($user->username);
        $this->assertNull($user->password);
        $stored = AccountActivationToken::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(hash('sha256', $token), $stored->token_hash);   // nur der Hash liegt in der Datenbank
        $this->assertEqualsWithDelta(72 * 3600, now()->diffInSeconds($stored->expires_at, false), 5);

        $this->get(route('activation.form', $token))->assertOk()->assertSee('Benutzername');
        $this->post(route('activation.complete', $token), ['username' => 'nora.neu', 'password' => 'geheim1234', 'password_confirmation' => 'geheim1234'])
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertFalse($user->isPending());
        $this->assertSame('nora.neu', $user->username);
        $this->assertNotNull($user->activated_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull(AccountActivationToken::query()->where('user_id', $user->id)->first());   // Einmal-Nutzung

        // der Link ist verbraucht
        $this->get(route('activation.form', $token))->assertSee('ungültig');
        $this->post('/login', ['username' => 'nora.neu', 'password' => 'geheim1234'])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_expired_and_unknown_links_are_rejected_and_a_pending_account_cannot_log_in(): void
    {
        [$user, $token] = $this->preparedAccount();
        AccountActivationToken::query()->where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->get(route('activation.form', $token))->assertSee('ungültig, abgelaufen');
        $this->post(route('activation.complete', $token), ['username' => 'nora.neu', 'password' => 'geheim1234', 'password_confirmation' => 'geheim1234'])
            ->assertRedirect(route('activation.invalid'));
        $this->get(route('activation.form', 'gibt-es-nicht'))->assertSee('ungültig, abgelaufen');
        $this->assertTrue($user->fresh()->isPending());

        $this->post('/login', ['username' => 'nora.neu', 'password' => 'irgendwas'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_requesting_a_new_link_is_always_answered_neutrally(): void
    {
        [$user] = $this->preparedAccount();
        $neutral = 'Falls zu dieser E-Mail-Adresse ein noch nicht aktiviertes Konto vorhanden ist';

        Mail::fake();
        $this->from(route('activation.request'))->post(route('activation.request.send'), ['email' => 'neu@example.test'])->assertSessionHas('status');
        Mail::assertSent(AccountActivationMail::class, 1);
        $this->post(route('activation.request.send'), ['email' => 'unbekannt@example.test'])->assertSessionHas('status');
        Mail::assertSent(AccountActivationMail::class, 1);   // für eine unbekannte Adresse geht nichts raus
        $this->assertStringContainsString($neutral, (string) session('status'));

        // höchstens drei Anforderungen je Adresse und Stunde
        RateLimiter::clear('activation:email:'.hash('sha256', 'neu@example.test'));
    }

    public function test_deactivated_person_cannot_log_in_and_loses_the_running_session(): void
    {
        $person = $this->person('aktiv@example.test');
        $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'username' => 'aktiv', 'email' => 'aktiv@example.test', 'password' => 'geheim1234']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $person->update(['active' => false]);

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login', ['hinweis' => 'konto-inaktiv']));
        $this->assertGuest();
        $this->post('/login', ['username' => 'aktiv', 'password' => 'geheim1234'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_password_reset_is_neutral_and_never_activates_a_pending_account(): void
    {
        [$pending] = $this->preparedAccount('wartet@example.test');
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'wartet@example.test'])->assertSessionHas('status');
        $this->post(route('password.email'), ['email' => 'unbekannt@example.test'])->assertSessionHas('status');
        Notification::assertNothingSent();

        $person = $this->person('aktiv2@example.test');
        User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'username' => 'aktiv2', 'email' => 'aktiv2@example.test']);
        $this->post(route('password.email'), ['email' => 'aktiv2@example.test'])->assertSessionHas('status');
        Notification::assertSentTimes(\Illuminate\Auth\Notifications\ResetPassword::class, 1);
    }

    public function test_strict_policy_rejects_weak_passwords_and_relaxed_allows_four_characters(): void
    {
        config(['auth.password_policy' => 'strict', 'auth.password_leak_check' => false]);
        [$user, $token] = $this->preparedAccount('streng@example.test');

        $this->post(route('activation.complete', $token), ['username' => 'streng', 'password' => 'abcd', 'password_confirmation' => 'abcd'])->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->isPending());
        $this->post(route('activation.complete', $token), ['username' => 'streng', 'password' => 'Sehr-Gut-123!x', 'password_confirmation' => 'Sehr-Gut-123!x'])->assertRedirect(route('login'));
        $this->assertFalse($user->fresh()->isPending());

        config(['auth.password_policy' => 'relaxed']);
        [$other, $otherToken] = $this->preparedAccount('locker@example.test');
        $this->post(route('activation.complete', $otherToken), ['username' => 'locker', 'password' => 'abcd', 'password_confirmation' => 'abcd'])->assertRedirect(route('login'));
        $this->assertFalse($other->fresh()->isPending());
    }

    public function test_setting_a_password_without_mail_is_reserved_for_the_super_admin(): void
    {
        $person = $this->person('ohne@example.test');
        $payload = ['username' => 'ohne.mail', 'email' => 'ohne@example.test', 'password' => 'geheim1234'];

        $this->actingAs($this->admin)->post(route('admin.personen.login.store', $person), $payload)->assertForbidden();

        $super = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'super_admin']);
        $this->actingAs($super)->post(route('admin.personen.login.store', $person), $payload)->assertRedirect();
        $this->assertSame('ohne.mail', User::query()->where('email', 'ohne@example.test')->value('username'));
    }

    public function test_an_inactive_person_gets_no_prepared_account(): void
    {
        Mail::fake();
        $person = $this->person('inaktiv@example.test', false);

        $this->actingAs($this->admin)->post(route('admin.personen.account.prepare', $person), ['email' => 'inaktiv@example.test'])->assertSessionHasErrors('email');
        $this->assertNull(User::query()->where('email', 'inaktiv@example.test')->first());
        Mail::assertNothingSent();
    }

    public function test_usernames_may_contain_umlauts_but_no_spaces_or_other_characters(): void
    {
        [$user, $token] = $this->preparedAccount('umlaut@example.test');
        $password = ['password' => 'geheim1234', 'password_confirmation' => 'geheim1234'];

        $this->post(route('activation.complete', $token), ['username' => 'ÜS DL'] + $password)->assertSessionHasErrors('username');
        $this->post(route('activation.complete', $token), ['username' => 'ÜS@DL'] + $password)->assertSessionHasErrors('username');
        $this->post(route('activation.complete', $token), ['username' => 'ÜS-DL'] + $password)->assertRedirect(route('login'));
        $this->assertSame('ÜS-DL', $user->fresh()->username);
    }
}
