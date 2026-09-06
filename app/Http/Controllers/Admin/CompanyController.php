<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Firmen verwalten - kleines Overlay, aus der Personenverwaltung heraus
 * per Link neben dem "Firma"-Feld öffenbar (Schritt 1 des von Ralf
 * angekündigten Musters, das später auf Abteilung/Geschäftsbereich/weitere
 * "klitzekleine Unterbereiche" übertragen wird - bewusst noch nicht
 * generisch abstrahiert, bis das Muster ein zweites Mal gebraucht wird).
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $companies = Company::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->withCount('people')
            ->orderBy('name')
            ->get();

        return view('admin.companies.partials.manage-body', ['companies' => $companies]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Company::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $name,
            'short_name' => trim((string) $request->string('short_name')) ?: mb_substr($name, 0, 20),
        ]);

        return redirect()->route('admin.companies');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->tenant_id === $request->user()->tenant_id, 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $company->update([
            'name' => $name,
            'short_name' => trim((string) $request->string('short_name')) ?: mb_substr($name, 0, 20),
        ]);

        return redirect()->route('admin.companies');
    }

    /**
     * Löschen - hat die Firma noch zugeordnete Personen, muss vorher ein
     * Ziel gewählt werden (eine andere Firma oder "nicht zugewiesen"),
     * gleiches Prinzip wie beim Löschen eines Rechte-Sets.
     */
    public function destroy(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->tenant_id === $request->user()->tenant_id, 404);

        if ($company->people()->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? Company::query()->where('tenant_id', $company->tenant_id)->where('id', '!=', $company->id)->find($request->integer('reassign_to'))
                : null;

            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);

            $company->people()->update(['company_id' => $reassignTo?->id]);
        }

        $company->delete();

        return redirect()->route('admin.companies');
    }
}
