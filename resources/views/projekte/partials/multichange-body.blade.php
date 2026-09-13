{{--
    Multichange (Ralf, 2026-09-13, nach Vietto-Analyse - Konzept in der
    Backlog-Memory) - drei vom Server bestimmte Zustände in EINEM Partial
    (gleiches Muster wie schedule-body.blade.php): $result gesetzt ->
    Ergebnis, sonst $preview gesetzt -> Vorschau/Bestätigen, sonst
    Formular (Feld+Wert wählen). Kein lokaler Alpine-Zustandsautomat -
    der Server entscheidet, welcher Zustand gerade dran ist.
--}}
@if (isset($result))
    <div class="space-y-3">
        <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-green-800">
            {{ __(':field: :count Projekt(e) erfolgreich geändert.', ['field' => $result['fieldLabel'], 'count' => $result['applied']]) }}
        </div>

        @if ($result['skipped']->isNotEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <div class="font-medium">
                    {{ __(':count Projekt(e) übersprungen (aktueller Workflow-Schritt bestimmt den Status):', ['count' => $result['skipped']->count()]) }}
                </div>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($result['skipped'] as $project)
                        <li>{{ $project->source_pn }} – {{ $project->title }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'multichange' }))"
            class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover"
        >
            {{ __('Schließen') }}
        </button>
    </div>
@elseif (isset($preview))
    <div class="space-y-3">
        <div class="text-gray-700">
            {{ __(':field wird auf „:value" gesetzt.', ['field' => $field['label'], 'value' => $valueLabel]) }}
        </div>

        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-700">
            {{ trans_choice(':count Projekt wird geändert.|:count Projekte werden geändert.', $preview['applicable']->count(), ['count' => $preview['applicable']->count()]) }}
        </div>

        @if ($preview['skipped']->isNotEmpty())
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <div class="font-medium">
                    {{ __(':count Projekt(e) werden übersprungen (aktueller Workflow-Schritt bestimmt den Status):', ['count' => $preview['skipped']->count()]) }}
                </div>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($preview['skipped'] as $project)
                        <li>{{ $project->source_pn }} – {{ $project->title }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($preview['applicable']->isEmpty())
            <div class="text-xs text-gray-400">{{ __('Keine Projekte übrig, auf die dies angewendet werden könnte.') }}</div>
        @endif

        <div
            class="flex gap-2"
            x-data="{
                async apply() {
                    const ok = await window.confirmDialog({
                        title: {{ \Illuminate\Support\Js::from(__('Wirklich anwenden?')) }},
                        message: {{ \Illuminate\Support\Js::from(__(':count Projekt(e) werden jetzt unwiderruflich geändert: „:field" auf „:value".', ['count' => $preview['applicable']->count(), 'field' => $field['label'], 'value' => $valueLabel])) }},
                        confirmLabel: {{ \Illuminate\Support\Js::from(__('Anwenden')) }},
                        cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
                    });
                    if (! ok) return;
                    await window.reloadMultichange(
                        {{ \Illuminate\Support\Js::from(route('projektgruppen.multichange.apply', $group)) }},
                        { field: {{ \Illuminate\Support\Js::from($field['key']) }}, value: {{ \Illuminate\Support\Js::from($value) }} }
                    );
                },
            }"
        >
            <button
                type="button"
                onclick="window.openMultichange({{ $group->id }})"
                class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
            >
                {{ __('Zurück') }}
            </button>
            @if ($preview['applicable']->isNotEmpty())
                <button
                    type="button"
                    @click="apply()"
                    class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover"
                >
                    {{ __('Anwenden') }}
                </button>
            @endif
        </div>
    </div>
@else
    <form
        x-data="{ field: {{ \Illuminate\Support\Js::from($selectedField ?? '') }}, value: '' }"
        @submit.prevent="window.reloadMultichange({{ \Illuminate\Support\Js::from(route('projektgruppen.multichange.preview', $group)) }}, { field, value })"
        class="space-y-3"
    >
        <div class="text-xs text-gray-400">
            {{ __('Betrifft alle Projekte der Gruppe „:name" - der aktuelle Stand wird erst beim Klick auf „Vorschau" ermittelt.', ['name' => $group->name]) }}
        </div>

        @if (isset($formErrors))
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                @foreach ($formErrors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        <div>
            <label class="block text-xs text-gray-500">{{ __('Feld') }}</label>
            <select x-model="field" @change="value = ''" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                <option value="">{{ __('– Feld wählen –') }}</option>
                @foreach ($fields as $f)
                    <option value="{{ $f['key'] }}">{{ $f['label'] }}</option>
                @endforeach
            </select>
        </div>

        @foreach ($fields as $f)
            <div x-show="field === {{ \Illuminate\Support\Js::from($f['key']) }}" x-cloak>
                @if (! empty($f['hint']))
                    <p class="mb-1 text-xs text-amber-700">{{ $f['hint'] }}</p>
                @endif
                <label class="block text-xs text-gray-500">{{ __('Neuer Wert') }}</label>
                @if ($f['type'] === 'text')
                    <input type="text" x-model="value" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                @elseif ($f['type'] === 'textarea')
                    <textarea x-model="value" rows="3" class="mt-0.5 w-full rounded-md border-gray-300 text-sm"></textarea>
                @elseif ($f['type'] === 'date')
                    <input type="date" x-model="value" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                @elseif ($f['type'] === 'select')
                    <select x-model="value" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– auswählen –') }}</option>
                        @foreach ($f['options'] as $optValue => $optLabel)
                            <option value="{{ $optValue }}">{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        @endforeach

        <button
            type="submit"
            :disabled="! field"
            class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('Vorschau') }}
        </button>
    </form>
@endif
