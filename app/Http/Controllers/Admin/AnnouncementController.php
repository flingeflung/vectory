<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AccessLevel;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Mitteilungen für die Startseite (Ralf, 2026-10-10): Super-Admins und Zentral-Admins wählen die Organisationen, an die
 * eine Mitteilung geht; Organisations-Admins erreichen nur ihre eigene Organisation (und sehen nur Mitteilungen, die
 * ausschließlich dorthin gehen). Bei einer Einzelinstallation gibt es keine Auswahl.
 */
class AnnouncementController extends Controller
{
    /** @return Collection<int, Tenant> */
    private function organizations(User $user): Collection
    {
        if (! SystemSetting::multiTenantEnabled()) {
            return Tenant::query()->whereKey(CurrentTenant::id())->get();
        }

        if (AccessLevel::canAccessAllOrganizations($user)) {
            return Tenant::query()->active()->orderBy('name')->get();
        }

        return Tenant::query()->whereKey($user->tenant_id)->get();
    }

    /** Mitteilungen, die der Benutzer sehen und bearbeiten darf. */
    private function visibleTo(User $user): Builder
    {
        $query = Announcement::query();

        if (AccessLevel::canAccessAllOrganizations($user)) {
            return $query;
        }

        $ids = $this->organizations($user)->pluck('id');

        return $query
            ->whereHas('tenants', fn (Builder $tenants) => $tenants->whereIn('tenants.id', $ids))
            ->whereDoesntHave('tenants', fn (Builder $tenants) => $tenants->whereNotIn('tenants.id', $ids));
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $announcements = $this->visibleTo($user)->with('tenants')->orderByDesc('created_at')->get();
        $selected = $request->filled('announcement')
            ? $announcements->firstWhere('id', (int) $request->query('announcement'))
            : null;

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'selectedAnnouncement' => $selected,
            'organizations' => $this->organizations($user),
            'chooseOrganizations' => $this->organizations($user)->count() > 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate(['text' => ['required', 'string', 'max:500']]);

        $announcement = Announcement::query()->create(['text' => trim($validated['text']), 'created_by_user_id' => $user->id]);
        // Wer nichts auswählen kann (eine Organisation), erreicht sofort diese; sonst wählt der Admin die Empfänger im Detail.
        $organizations = $this->organizations($user);
        if ($organizations->count() === 1) {
            $announcement->tenants()->sync($organizations->pluck('id'));
        }

        return redirect()->route('admin.mitteilungen', ['announcement' => $announcement->id])->with('status', 'announcements-updated');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->visibleTo($user)->whereKey($announcement->id)->exists(), 404);

        $allowedIds = $this->organizations($user)->pluck('id')->map(fn ($id) => (int) $id);
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'ends_on' => ['nullable', 'date'],
            'tenant_ids' => ['nullable', 'array'],
            'tenant_ids.*' => ['integer'],
        ]);

        $announcement->update(['text' => trim($validated['text']), 'ends_on' => $validated['ends_on'] ?? null]);

        $tenantIds = $allowedIds->count() === 1
            ? $allowedIds
            : collect($validated['tenant_ids'] ?? [])->map(fn ($id) => (int) $id)->intersect($allowedIds)->unique();
        $announcement->tenants()->sync($tenantIds->values());

        return redirect()->route('admin.mitteilungen', ['announcement' => $announcement->id])->with('status', 'announcements-updated');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($this->visibleTo($request->user())->whereKey($announcement->id)->exists(), 404);

        $announcement->delete();

        return redirect()->route('admin.mitteilungen')->with('status', 'announcements-updated');
    }
}
