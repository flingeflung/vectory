<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PresetCopy\PresetCopier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Zentrale Seite "Konfiguration übernehmen" - nur Zentral-/Super-Admin (Ralf, 2026-10-03). */
class PresetCopyController extends Controller
{
    public function __construct(private readonly PresetCopier $copier) {}

    public function index(Request $request): View
    {
        $tenants = Tenant::query()->active()->orderBy('name')->get();
        $source = $tenants->firstWhere('id', $request->integer('source'));
        $target = $tenants->firstWhere('id', $request->integer('target'));
        $ready = $source && $target && $source->id !== $target->id;

        $areas = $ready
            ? collect($this->copier->areas())->map(fn ($area) => [
                'key' => $area->key(),
                'label' => $area->label(),
                'items' => $area->items($source->id, $target->id),
            ])->all()
            : [];

        return view('admin.voreinstellungen.index', [
            'tenants' => $tenants,
            'source' => $source,
            'target' => $target,
            'ready' => $ready,
            'areas' => $areas,
            'report' => session('preset_report'),
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', 'integer'],
            'target' => ['required', 'integer', 'different:source'],
        ]);
        abort_unless(Tenant::query()->active()->whereIn('id', [$data['source'], $data['target']])->count() === 2, 422);

        // Nur angehakte Einträge: sel[bereich][eintrag] = 1, act[bereich][eintrag] = Aktion bei Gleichnamigem.
        $choices = [];
        foreach ((array) $request->input('sel', []) as $area => $entries) {
            foreach (array_keys((array) $entries) as $key) {
                $action = (string) data_get($request->input('act', []), [$area, $key], 'copy');
                $choices[$area][$key] = in_array($action, ['overwrite', 'rename'], true) ? $action : 'copy';
            }
        }

        if ($choices === []) {
            return back()->with('notice', __('Es wurde nichts ausgewählt.'));
        }

        $report = $this->copier->apply((int) $data['source'], (int) $data['target'], $choices);

        return redirect()->route('admin.voreinstellungen', ['source' => $data['source'], 'target' => $data['target']])
            ->with('preset_report', $report->lines());
    }
}
