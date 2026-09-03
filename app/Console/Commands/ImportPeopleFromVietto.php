<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Department;
use App\Models\LegacyRole;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Personen aus Vietto (personen, rein lesend) mit Klarnamen -
 * auf Ralfs Wunsch unanonymisiert übernommen, damit er gut testen kann
 * (er verfremdet bei Bedarf selbst). Einzige Ausnahme: die E-Mail-Domain
 * "viega.de" wird zu "vectory.de", alles andere bleibt unverändert. Nur
 * intTyp 1 (Login-User) und 2 (E-Mail-Kontakt) - intTyp 99 (automatischer
 * Prozess) sind keine echten Personen.
 */
class ImportPeopleFromVietto extends Command
{
    protected $signature = 'people:import-from-vietto';

    protected $description = 'Personen aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $companyIds = Company::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $departmentIds = Department::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $legacyRoleIds = LegacyRole::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        $sanitizeDate = function (?string $value): ?string {
            if (! $value || ! preg_match('/^(\d{4})-\d{2}-\d{2}/', $value, $matches)) {
                return null;
            }

            return ((int) $matches[1]) < 1000 ? null : $value;
        };

        $rows = DB::connection('vietto')->table('personen')
            ->whereIn('intTyp', [1, 2])
            ->orderBy('intSort')
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $email = $row->strEmail ? str_ireplace('viega.de', 'vectory.de', $row->strEmail) : null;

            Person::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                [
                    'first_name' => $row->strVname,
                    'last_name' => $row->strNname,
                    'email' => $email,
                    'company_id' => $companyIds->get($row->valFirmaID),
                    'department_id' => $row->valBereichID > 0 ? $departmentIds->get($row->valBereichID) : null,
                    'legacy_role_id' => $legacyRoleIds->get($row->valRolleID),
                    'last_login_at' => $sanitizeDate($row->dtgLastLogin),
                    'start_date' => $sanitizeDate($row->dtgStartDate),
                    'end_date' => $sanitizeDate($row->dtgEndDate),
                    'remarks' => $row->strBemerk ?: null,
                    'language' => $row->strLang ?: null,
                    'sort' => $row->intSort,
                    'active' => (bool) $row->blnActive,
                ]
            );
            $count++;
        }

        // Ralfs eigener Vectory-Account (bereits vorhanden) mit seiner
        // Vietto-Person (valID=1) verknüpfen.
        $ralfPerson = Person::where('tenant_id', $tenant->id)->where('legacy_id', 1)->first();
        if ($ralfPerson) {
            User::where('email', 'ergetr65@googlemail.com')->update(['person_id' => $ralfPerson->id]);
        }

        $this->info("{$count} Personen importiert.");

        return self::SUCCESS;
    }
}
