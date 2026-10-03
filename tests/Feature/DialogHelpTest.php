<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\HelpArticleTranslation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DialogId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialogHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_dialog_id_ignores_record_counter_and_is_stable(): void
    {
        $this->assertSame(DialogId::for('projektgruppen-panel-12'), DialogId::for('projektgruppen-panel-7'));
        $this->assertSame(DialogId::for('project-overlay'), DialogId::for('project-overlay'));
        $this->assertMatchesRegularExpression('/^D-[0-9A-Z]{4}$/', DialogId::for('project-overlay'));
    }

    public function test_every_dialog_is_registered_with_a_fixed_id_and_none_vanished(): void
    {
        $names = \App\Console\Commands\SyncDialogIds::modalNamesInViews();
        $registered = array_keys((array) config('dialog-ids'));

        $this->assertGreaterThan(20, count($names));

        $missing = array_values(array_diff($names, $registered));
        $this->assertSame([], $missing, 'Neue Dialoge ohne feste ID - bitte `php artisan dialogs:sync` ausführen: '.implode(', ', $missing));

        // Ein Dialog, der aus den Views verschwunden ist, wurde umbenannt oder entfernt: den neuen Namen
        // mit der ALTEN ID in config/dialog-ids.php eintragen (sonst verliert die Hilfeseite die Zuordnung)
        // und den alten Eintrag löschen. Dynamisch benannte Dialoge (Name aus Variablen) fehlen hier bewusst.
        $vanished = array_values(array_diff($registered, $names));
        $dynamic = ['projektgruppen-panel', 'projektgruppen-panel-uebersicht'];
        $vanished = array_values(array_diff($vanished, $dynamic));
        $this->assertSame([], $vanished, 'Dialog umbenannt/entfernt? Neuen Namen mit der alten ID in config/dialog-ids.php eintragen: '.implode(', ', $vanished));
    }

    public function test_fixed_ids_are_unique(): void
    {
        $ids = array_values((array) config('dialog-ids'));
        $this->assertSame(count($ids), count(array_unique($ids)), 'Doppelte Dialog-IDs in config/dialog-ids.php');
    }

    public function test_help_prefers_article_of_the_dialog_and_falls_back_to_the_page(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $dialogId = DialogId::for('project-overlay');

        $page = HelpArticle::query()->create(['key' => 'seite', 'route_names' => ['projekte']]);
        HelpArticleTranslation::query()->create(['help_article_id' => $page->id, 'locale' => 'de', 'title' => 'Hilfe zur Seite', 'body' => 'Seitentext']);
        $dialog = HelpArticle::query()->create(['key' => 'dialog', 'route_names' => [$dialogId]]);
        HelpArticleTranslation::query()->create(['help_article_id' => $dialog->id, 'locale' => 'de', 'title' => 'Hilfe zum Dialog', 'body' => 'Dialogtext']);

        $this->actingAs($user);

        $this->get(route('hilfe', ['route' => 'projekte', 'dialog' => $dialogId]))
            ->assertOk()->assertSee('Dialogtext')->assertDontSee('Seitentext');

        $this->get(route('hilfe', ['route' => 'projekte', 'dialog' => DialogId::for('irgendein-anderer-dialog')]))
            ->assertOk()->assertSee('Seitentext')->assertSee('noch keine eigene Hilfeseite');

        $this->get(route('hilfe', ['route' => 'projekte']))
            ->assertOk()->assertSee('Seitentext');
    }

    public function test_modal_shows_copy_id_always_and_help_question_mark_only_with_article(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']));
        $id = DialogId::for('test-dialog-xyz');

        $without = \Illuminate\Support\Facades\Blade::render('<x-modal name="test-dialog-xyz">Inhalt</x-modal>');
        $this->assertStringContainsString($id, $without);
        $this->assertStringNotContainsString('Hilfe zu diesem Dialog"', $without);

        HelpArticle::query()->create(['key' => 'dlg', 'route_names' => [$id]]);
        DialogId::forgetCache();

        $with = \Illuminate\Support\Facades\Blade::render('<x-modal name="test-dialog-xyz">Inhalt</x-modal>');
        $this->assertStringContainsString('aria-label="Hilfe zu diesem Dialog"', $with);
        DialogId::forgetCache();
    }

    public function test_support_page_lists_dialog_ids_for_super_admin_only(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $id = DialogId::for('project-overlay');

        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']))
            ->get(route('admin.dialog-ids'))->assertRedirect();

        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']))
            ->get(route('admin.dialog-ids'))
            ->assertOk()
            ->assertSee($id)
            ->assertSee('project-overlay')
            ->assertSee('app.blade.php', false);
    }
}
