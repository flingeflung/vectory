<?php

namespace App\Support;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Organisationsübergreifende Listen (Aufgaben, Illustrationen; Ralf, 2026-10-09): Mitarbeiter der Heimat-Organisation
 * wollen nicht ständig zwischen Organisationen wechseln. Welche Organisationen jemand einbeziehen darf, regelt dieselbe
 * Zugriffsprüfung wie bei den Kritischen Projekten (CriticalProjectAccess::organizations).
 */
final class OrganizationSelection
{
    /** @return Collection<int, \App\Models\Tenant> */
    public static function allowed(User $user): Collection
    {
        return CriticalProjectAccess::organizations($user);
    }

    /**
     * @param  list<int|string>|null  $stored  null = nie ausgewählt, dann alle erlaubten
     * @return Collection<int, int>
     */
    public static function selected(?array $stored, Collection $allowed): Collection
    {
        $allowedIds = $allowed->pluck('id')->map(fn ($id) => (int) $id);

        if (! SystemSetting::multiTenantEnabled() || $stored === null) {
            return $allowedIds->values();
        }

        return collect($stored)->map(fn ($id) => (int) $id)->intersect($allowedIds)->unique()->values();
    }

    /**
     * Auswahl aus dem Filterformular: Fehlt der Marker, wurde die Gruppe nie angefasst (null); steht er da, aber kein
     * Haken, ist bewusst keine Organisation gewählt.
     *
     * @return list<string>|null
     */
    public static function fromRequest(Request $request): ?array
    {
        return $request->has('organizations_submitted')
            ? array_map('strval', (array) $request->query('organizations', []))
            : null;
    }

    /**
     * Verweise aus Zeilen anderer Organisationen: erst dorthin wechseln, dann Projekt bzw. Illustrationsaufträge
     * öffnen. Gibt eine Weiterleitung zurück oder null, wenn der Aufruf kein solcher Verweis ist.
     */
    public static function handleOpenRequest(Request $request, string $route): ?RedirectResponse
    {
        $key = $request->filled('open_project') ? 'open_project' : ($request->filled('open_orders') ? 'open_orders' : null);
        if ($key === null) {
            return null;
        }

        $allowedIds = self::allowed($request->user())->pluck('id');
        $target = Project::withoutGlobalScope('tenant')->whereIn('tenant_id', $allowedIds)->findOrFail((int) $request->query($key));

        if (SystemSetting::multiTenantEnabled() && CurrentTenant::id() !== (int) $target->tenant_id) {
            CurrentTenant::switchTo((int) $target->tenant_id);
        }

        return redirect()->route($route)->with($key === 'open_project' ? 'open_project_id' : 'open_orders_project_id', $target->id);
    }
}
