<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HidesInactiveOrganizationPersons;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/**
 * Wächter für "Organisation deaktivieren" (Ralf, 2026-10-03): Neue Funktionen dürfen die Regel nicht
 * unbemerkt umgehen. Die drei Tests schlagen an, sobald etwas Neues gebaut wird, das Daten einer
 * deaktivierten Organisation anzeigen könnte. Ausnahmen stehen unten mit Begründung - eine neue
 * Ausnahme gehört erst nach einer Prüfung hierher, nicht einfach um den Test zu beruhigen.
 */
class OrganizationGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Modelle mit tenant_id-Spalte ohne BelongsToTenant - jeweils geprüft. */
    private const TENANT_COLUMN_WITHOUT_SCOPE = [
        'ProjectChecklist' => 'hängt am Projekt (project_id); ist das Projekt ausgeblendet, ist es auch die Zuordnung',
        'ProjectChecklistPoint' => 'wie ProjectChecklist: nur über das Projekt erreichbar',
        'ProjectGanttPreference' => 'persönliche Einstellung eines Nutzers, keine Organisationsdaten',
        'User' => 'Anmeldung und laufende Sitzung sperrt EnsureOrganizationIsActive/LoginRequest; Listen laufen über Person',
    ];

    /** Modelle mit person_id-Spalte ohne HidesInactiveOrganizationPersons - jeweils geprüft. */
    private const PERSON_COLUMN_WITHOUT_LINK_SCOPE = [
        'CalendarEntry' => 'nur über sichtbare Personen erreichbar (CalendarController::visiblePeople, Person-Schutzregel)',
        'PersonVacationDays' => 'Historie der Person, nur über die (ausblendbare) Person erreichbar',
        'PersonWeeklyHours' => 'Historie der Person, nur über die (ausblendbare) Person erreichbar',
        'PlanningPersonBaseLoad' => 'Grundlast der Person, nur über die (ausblendbare) Person erreichbar',
        'User' => 'siehe oben (Anmeldesperre)',
    ];

    /**
     * Bereits geprüfte rohe DB::table()-Abfragen auf Tabellen mit tenant_id: Datei::Tabelle => Anzahl.
     * Rohe Abfragen umgehen die Schutzregeln und müssen Tenant::inactiveIds() selbst beachten bzw. durch
     * die aktive Organisation oder bereits gefilterte Projekt-IDs eingegrenzt sein.
     */
    private const REVIEWED_RAW_QUERIES = [
        // Schreibt berechnete Termine nur für die übergebene Projekt-ID (Update über Projekt-/Schritt-ID, keine Auswertung)
        'app/Services/ProjectScheduler.php::projects' => 1,
        'app/Services/ProjectScheduler.php::project_workflow_steps' => 1,
        // Liefert nur die IDs der Schritte anderer Workflows, um EIN Projekt (übergebene ID) nach einem Workflow-Wechsel aufzuräumen; gelöscht wird nur über project_id
        'app/Models/ProjectWorkflowStep.php::workflow_steps' => 1,
        // Setzt das Anmelde-Merkmal genau eines Benutzers (übergebene ID) neu
        'app/Support/SessionRevoker.php::users' => 1,
        'app/Http/Controllers/Admin/AttributeController.php::projects' => 5,
        'app/Http/Controllers/Admin/FunctionGroupController.php::function_group_tenant' => 1,
        'app/Http/Controllers/Admin/FunctionGroupController.php::project_function_group_hours' => 1,
        'app/Http/Controllers/Admin/FunctionGroupController.php::project_people' => 1,
        'app/Http/Controllers/Admin/FunctionGroupController.php::project_template_function_group' => 1,
        'app/Http/Controllers/Admin/FunctionGroupController.php::project_workflow_step_people' => 1,
        'app/Http/Controllers/Admin/FunctionGroupController.php::tasks' => 1,
        'app/Http/Controllers/Admin/FunctionGroupController.php::workflow_step_function_group' => 1,
        'app/Http/Controllers/Admin/JobTypeController.php::job_groups' => 7,
        'app/Http/Controllers/Admin/JobTypeController.php::job_types' => 4,
        'app/Http/Controllers/Admin/PersonController.php::person_tenant' => 1,
        'app/Http/Controllers/Admin/ProjectTypeController.php::projects' => 4,
        'app/Http/Controllers/CalendarController.php::person_tenant' => 1,
        'app/Http/Controllers/CriticalProjectController.php::job_hours' => 1,
        'app/Http/Controllers/GraphicOrderController.php::person_tenant' => 1,
        'app/Http/Controllers/JobloadController.php::job_hours' => 5,
        'app/Http/Controllers/JobloadController.php::job_types' => 3,
        'app/Http/Controllers/JobloadController.php::people' => 2,
        'app/Http/Controllers/JobloadController.php::person_job_types' => 3,
        'app/Http/Controllers/JobloadOverviewController.php::job_groups' => 1,
        'app/Http/Controllers/JobloadOverviewController.php::job_hours' => 5,
        'app/Http/Controllers/JobloadOverviewController.php::job_types' => 2,
        'app/Http/Controllers/JobloadOverviewController.php::people' => 1,
        'app/Http/Controllers/JobloadOverviewController.php::person_tenant' => 1,
        'app/Http/Controllers/JobloadOverviewController.php::users' => 1,
        'app/Http/Controllers/MultichangeController.php::project_people' => 4,
        'app/Http/Controllers/PlanningController.php::person_tenant' => 1,
        'app/Http/Controllers/PlanningController.php::person_weekly_hours' => 1,
        'app/Http/Controllers/ProjectController.php::job_hours' => 7,
        'app/Http/Controllers/ProjectController.php::people' => 1,
        'app/Http/Controllers/ProjectController.php::person_tenant' => 1,
        'app/Http/Controllers/ProjectController.php::project_job_types' => 1,
        'app/Http/Controllers/ProjectController.php::projects' => 3,
        'app/Http/Controllers/ProjectHourController.php::job_hours' => 11,
        'app/Http/Controllers/ProjectHourController.php::project_job_types' => 2,
        'app/Http/Controllers/ProjectHourController.php::projects' => 2,
        'app/Http/Controllers/ProjectJobTypeController.php::job_types' => 2,
        'app/Http/Controllers/ProjectJobTypeController.php::project_job_types' => 5,
        'app/Http/Controllers/ProjectPercentageSplitController.php::projects' => 2,
        'app/Models/Person.php::graphic_orders' => 1,
        'app/Models/Person.php::person_tenant' => 1,
        'app/Models/Person.php::project_people' => 1,
        'app/Models/Person.php::project_workflow_step_people' => 1,
        'app/Models/Person.php::project_workflow_steps' => 1,
        'app/Models/Person.php::tasks' => 1,
        'app/Models/Tenant.php::people' => 1,
        'app/Models/Tenant.php::projects' => 1,
        // Konfiguration übernehmen: reine Zuordnungstabellen, nur über ausdrückliche Schritt-/Schablonen-IDs zweier aktiver Organisationen (Controller prüft active()).
        'app/Services/PresetCopy/JobTypesArea.php::job_groups' => 3, // jeweils mit ausdrücklicher tenant_id einer gewählten, aktiven Organisation
        'app/Services/PresetCopy/JobTypesArea.php::job_types' => 3,
        'app/Services/PresetCopy/ProjectTemplatesArea.php::project_template_function_group' => 3,
        'app/Services/PresetCopy/WorkflowsArea.php::workflow_step_function_group' => 2,
        // Endgültiges Löschen einer Organisation: gelöscht wird gezielt alles mit der tenant_id der zu löschenden Organisation
        // (bewusst ohne die Schutzregeln, auch wenn sie deaktiviert ist) - in einer Reihenfolge, die die RESTRICT-Fremdschlüssel erfüllt.
        'app/Services/TenantPurger.php::job_types' => 1,
        'app/Services/TenantPurger.php::paper_format_combinations' => 1,
        'app/Services/TenantPurger.php::people' => 1,
        'app/Services/TenantPurger.php::project_notes' => 1,
        'app/Services/TenantPurger.php::projects' => 1,
        'app/Support/CurrentTenant.php::person_tenant' => 2,
    ];

    /** @return array<class-string, string> Modellklasse => Tabelle */
    private function models(): array
    {
        $models = [];
        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }
            $models[$class] = (new $class)->getTable();
        }

        return $models;
    }

    public function test_models_with_a_tenant_column_use_the_tenant_scope(): void
    {
        $missing = [];
        foreach ($this->models() as $class => $table) {
            $short = class_basename($class);
            if (Schema::hasColumn($table, 'tenant_id')
                && ! in_array(BelongsToTenant::class, class_uses_recursive($class), true)
                && ! array_key_exists($short, self::TENANT_COLUMN_WITHOUT_SCOPE)) {
                $missing[] = $short;
            }
        }

        $this->assertSame([], $missing, 'Modell mit tenant_id ohne BelongsToTenant: '.implode(', ', $missing)
            .'. Ohne diesen Zusatz bleiben Daten deaktivierter Organisationen sichtbar. Zusatz einbinden - oder, nach Prüfung, unter TENANT_COLUMN_WITHOUT_SCOPE mit Begründung freigeben.');
    }

    public function test_models_that_assign_persons_hide_persons_of_deactivated_organizations(): void
    {
        $missing = [];
        foreach ($this->models() as $class => $table) {
            $short = class_basename($class);
            if (Schema::hasColumn($table, 'person_id')
                && ! in_array(HidesInactiveOrganizationPersons::class, class_uses_recursive($class), true)
                && ! array_key_exists($short, self::PERSON_COLUMN_WITHOUT_LINK_SCOPE)) {
                $missing[] = $short;
            }
        }

        $this->assertSame([], $missing, 'Modell mit person_id ohne HidesInactiveOrganizationPersons: '.implode(', ', $missing)
            .'. Ohne diesen Zusatz bricht die Ansicht ab, sobald die Person ausgeblendet ist. Zusatz einbinden - oder, nach Prüfung, unter PERSON_COLUMN_WITHOUT_LINK_SCOPE mit Begründung freigeben.');
    }

    public function test_no_unreviewed_raw_queries_on_tenant_tables(): void
    {
        $tenantTables = collect(DB::select('SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = "tenant_id"'))
            ->pluck('t')->all();

        $found = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if (str_starts_with($relative, 'app/Console/')) {
                continue; // Einmal-Importe aus Vietto und Hilfsbefehle, nicht Teil der Oberfläche
            }
            preg_match_all("/DB::table\\(\\s*['\"]([a-z_]+)['\"]/", file_get_contents($file->getPathname()), $matches);
            foreach ($matches[1] as $table) {
                if (in_array($table, $tenantTables, true)) {
                    $key = $relative.'::'.$table;
                    $found[$key] = ($found[$key] ?? 0) + 1;
                }
            }
        }

        $unreviewed = [];
        foreach ($found as $key => $count) {
            $reviewed = self::REVIEWED_RAW_QUERIES[$key] ?? 0;
            if ($count > $reviewed) {
                $unreviewed[] = $key.' ('.$count.' statt '.$reviewed.' geprüft)';
            }
        }

        $this->assertSame([], $unreviewed, 'Neue rohe DB::table()-Abfrage auf eine Tabelle mit tenant_id: '.implode('; ', $unreviewed)
            .'. Rohe Abfragen umgehen die Schutzregeln: Wenn sie Personen, Projekte oder Auswertungen betreffen, Tenant::inactiveIds() ausschließen (whereNotIn) bzw. nur über bereits gefilterte IDs arbeiten. '
            .'Wenn geprüft, die Zahl unter REVIEWED_RAW_QUERIES erhöhen.');
    }
}
