<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Kritische Projekte') }}</h2>
    </x-slot>

    @php
        $severityClasses = [
            'blocked' => 'border-red-200 bg-red-50 text-red-700',
            'critical' => 'border-orange-200 bg-orange-50 text-orange-700',
            'watch' => 'border-amber-200 bg-amber-50 text-amber-700',
        ];
    @endphp

    <div class="flex h-full flex-col p-4 sm:p-6 lg:p-8">
        <div class="mx-auto flex min-h-0 w-full max-w-[100rem] flex-1 flex-col">
            <form method="GET" action="{{ route('critical-projects.index') }}" x-data="{ submitting: false, applyFilter(event) { if (this.submitting) return; this.submitting = true; this.$nextTick(() => event.target.form.requestSubmit()); } }" @submit="submitting = true" :class="{ 'cursor-wait': submitting }" class="mb-3 flex shrink-0 flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-3 text-sm">
                @foreach ($selectedIds as $id)<input type="hidden" name="organizations[]" value="{{ $id }}">@endforeach
                <label class="grid gap-1">
                    <span class="text-xs text-gray-500">{{ __('Schweregrad') }}</span>
                    <select name="severity" @change="applyFilter($event)" :class="{ 'pointer-events-none opacity-60': submitting }" class="rounded-md border-gray-300 py-1.5 text-sm">
                        <option value="">{{ __('Alle') }}</option>
                        <option value="blocked" @selected($severity === 'blocked')>{{ __('Handlungsbedarf') }}</option>
                        <option value="critical" @selected($severity === 'critical')>{{ __('Kritisch') }}</option>
                        <option value="watch" @selected($severity === 'watch')>{{ __('Beobachten') }}</option>
                    </select>
                </label>
                <label class="grid min-w-64 gap-1">
                    <span class="text-xs text-gray-500">{{ __('Grund') }}</span>
                    <select name="reason" @change="applyFilter($event)" :class="{ 'pointer-events-none opacity-60': submitting }" class="rounded-md border-gray-300 py-1.5 text-sm">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($evaluator->definitions() as $definition)
                            <option value="{{ $definition['code'] }}" @selected($reason === $definition['code'])>{{ $definition['title'] }}</option>
                        @endforeach
                    </select>
                </label>
                <a href="{{ route('critical-projects.index', ['organizations' => $selectedIds->all()]) }}" @click="submitting = true" :class="{ 'pointer-events-none opacity-60': submitting }" class="inline-flex items-center gap-1.5 rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    {{ __('Filter zurücksetzen') }}
                </a>
                <span x-show="submitting" x-cloak class="self-center text-gray-500"><x-loading-spinner class="h-4 w-4" /></span>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'critical-project-organizations' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Organisationen') }} ({{ $selectedIds->count() }})</button>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'critical-project-rules' }))" class="ml-auto rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100">{{ __('Regelwerk') }}</button>
            </form>

            @if ($hiddenOtherCount > 0)
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'critical-project-organizations' }))" class="mb-3 shrink-0 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-left text-sm text-blue-700 hover:bg-blue-100">
                    {{ trans_choice(':count weiteres kritisches Projekt in anderen Organisationen|:count weitere kritische Projekte in anderen Organisationen', $hiddenOtherCount, ['count' => $hiddenOtherCount]) }}
                </button>
            @endif

            <div class="mb-1 shrink-0 text-xs text-gray-500">
                {{ trans_choice(':count kritisches Projekt|:count kritische Projekte', $rows->count(), ['count' => $rows->count()]) }}
            </div>
            <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <x-sortable-th field="pn" :sort="$sort" :direction="$direction">{{ __('PN / Bezeichnung') }}</x-sortable-th>
                            <x-sortable-th field="workflow" :sort="$sort" :direction="$direction">{{ __('Workflow / aktueller Schritt') }}</x-sortable-th>
                            <x-sortable-th field="status" :sort="$sort" :direction="$direction">{{ __('Status') }}</x-sortable-th>
                            <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500">{{ __('Projektbeteiligte') }}</th>
                            <x-sortable-th field="severity" :sort="$sort" :direction="$direction">{{ __('Gründe') }}</x-sortable-th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            @php($project = $row['project'])
                            <tr class="align-top hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium"><x-pn-link :project="$project" /></div>
                                    <div class="max-w-80 truncate text-gray-700" title="{{ $project->title }}">{{ $project->title }}</div>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-400">
                                        <x-organization-icon :organization="$project->tenant" class="h-3.5 w-3.5" />
                                        <span>{{ $project->tenant?->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-800">{{ $project->workflow?->name ?? '–' }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['current_step'] ?? __('Kein aktueller Schritt') }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $project->status_label }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex max-w-64 flex-wrap gap-1">
                                        @forelse ($project->projectPeople->filter(fn ($assignment) => $assignment->person)->unique('person_id') as $assignment)
                                            @php($groups = $project->projectPeople->where('person_id', $assignment->person_id)->pluck('functionGroup.short_name')->filter()->join(', '))
                                            <span title="{{ $assignment->person->fullName() }}{{ $groups ? ' · '.$groups : '' }}" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ $assignment->person->short_name ?: mb_strtoupper(mb_substr($assignment->person->first_name ?? '', 0, 1).mb_substr($assignment->person->last_name ?? '', 0, 1)) }}</span>
                                        @empty
                                            <span class="text-xs text-gray-400">{{ __('Keine') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="min-w-96 px-4 py-3">
                                    <div class="space-y-2">
                                        @foreach ($row['findings'] as $finding)
                                            <div>
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="rounded border px-1.5 py-0.5 text-[11px] font-semibold {{ $severityClasses[$finding['severity']] }}">{{ $finding['severity_label'] }}</span>
                                                    <span class="font-medium text-gray-800">{{ $finding['title'] }}</span>
                                                </div>
                                                <div class="mt-0.5 text-xs text-gray-600">{{ $finding['detail'] }}</div>
                                                <div class="text-xs text-gray-400"><span class="font-medium">{{ __('Lösungshinweis') }}:</span> {{ $finding['solution'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">{{ __('Für die gewählten Filter wurden keine kritischen Projekte gefunden.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal name="critical-project-organizations" max-width="md" :draggable="true">
        <form method="GET" action="{{ route('critical-projects.index') }}" x-data="{ submitting: false }" @submit="submitting = true" :class="{ 'cursor-wait': submitting }">
            <input type="hidden" name="severity" value="{{ $severity }}"><input type="hidden" name="reason" value="{{ $reason }}">
            <div data-drag-handle class="flex cursor-move items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3">
                <h3 class="text-sm font-semibold">{{ __('Organisationen auswählen') }}</h3>
                <button type="button" @click="$dispatch('close-modal', 'critical-project-organizations')" class="text-xl text-gray-400">×</button>
            </div>
            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
                <button type="button" @click="$refs.list.querySelectorAll('input[type=checkbox]').forEach(i => i.checked = true)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Alle') }}</button>
                <button type="button" @click="$refs.list.querySelectorAll('input[type=checkbox]').forEach(i => i.checked = false)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Keiner') }}</button>
            </div>
            <div x-ref="list" class="max-h-[60vh] space-y-1 overflow-y-auto p-4 text-sm">
                @foreach ($organizations as $organization)
                    <label class="flex items-center gap-2 rounded px-1 py-1 hover:bg-gray-50"><input type="checkbox" name="organizations[]" value="{{ $organization->id }}" @checked($selectedIds->contains($organization->id)) class="rounded border-gray-300 text-blue-600"><span>{{ $organization->name }}</span></label>
                @endforeach
            </div>
            <div class="flex justify-end gap-2 border-t p-3"><button type="button" @click="$dispatch('close-modal', 'critical-project-organizations')" class="rounded-md border px-3 py-1.5 text-xs">{{ __('Abbrechen') }}</button><button type="submit" :disabled="submitting" class="inline-flex items-center gap-1.5 rounded-md bg-btn-primary px-3 py-1.5 text-xs text-white disabled:cursor-wait disabled:opacity-50"><span x-show="submitting" x-cloak><x-loading-spinner class="h-3.5 w-3.5 text-white" /></span>{{ __('Anwenden') }}</button></div>
        </form>
    </x-modal>

    <x-modal name="critical-project-rules" max-width="3xl" :draggable="true">
        <div data-drag-handle class="flex cursor-move items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3"><h3 class="font-semibold">{{ __('Regelwerk für kritische Projekte') }}</h3><button type="button" @click="$dispatch('close-modal', 'critical-project-rules')" class="text-xl text-gray-400">×</button></div>
        <div class="max-h-[70vh] space-y-3 overflow-y-auto p-4 text-sm">
            <p class="text-gray-600">{{ __('Ein aktives Projekt erscheint, sobald mindestens eine der folgenden Regeln zutrifft.') }}</p>
            <section class="rounded-md border border-blue-100 bg-blue-50/40 p-3">
                <h4 class="mb-2 font-semibold text-gray-900">{{ __('Bedeutung der Signale') }}</h4>
                <div class="grid gap-2 md:grid-cols-3">
                    <div class="rounded-md bg-white p-2.5 shadow-sm">
                        <span class="rounded border px-1.5 py-0.5 text-[11px] font-semibold {{ $severityClasses['blocked'] }}">{{ __('Handlungsbedarf') }}</span>
                        <p class="mt-1.5 text-xs text-gray-600">{{ __('Eine unmittelbar benötigte Voraussetzung fehlt. Der aktuelle Projektschritt kann nicht zuverlässig weitergeführt werden und verlangt sofortige Klärung.') }}</p>
                    </div>
                    <div class="rounded-md bg-white p-2.5 shadow-sm">
                        <span class="rounded border px-1.5 py-0.5 text-[11px] font-semibold {{ $severityClasses['critical'] }}">{{ __('Kritisch') }}</span>
                        <p class="mt-1.5 text-xs text-gray-600">{{ __('Eine konkrete Abweichung gefährdet Termin, Ablauf oder Budget. Das Projekt sollte zeitnah geprüft und eine Maßnahme festgelegt werden.') }}</p>
                    </div>
                    <div class="rounded-md bg-white p-2.5 shadow-sm">
                        <span class="rounded border px-1.5 py-0.5 text-[11px] font-semibold {{ $severityClasses['watch'] }}">{{ __('Beobachten') }}</span>
                        <p class="mt-1.5 text-xs text-gray-600">{{ __('Es gibt einen frühen Hinweis oder eine künftig benötigte Angabe fehlt. Noch besteht kein akutes Hindernis, eine Prüfung ist jedoch sinnvoll.') }}</p>
                    </div>
                </div>
            </section>
            @foreach ($evaluator->definitions() as $definition)
                <section class="rounded-md border border-gray-200 p-3"><div class="flex items-center gap-2"><h4 class="font-semibold text-gray-900">{{ $definition['title'] }}</h4><span class="rounded border px-1.5 py-0.5 text-[11px] {{ $severityClasses[$definition['severity']] }}">{{ $definition['severity_label'] }}</span></div><p class="mt-1 text-gray-700">{{ $definition['description'] }}</p><p class="mt-1 text-xs text-gray-500"><span class="font-medium">{{ __('Zählt nicht') }}:</span> {{ $definition['exclusion'] }}</p><p class="text-xs text-gray-500"><span class="font-medium">{{ __('Lösungshinweis') }}:</span> {{ $definition['solution'] }}</p></section>
            @endforeach
        </div>
    </x-modal>
</x-app-layout>
