<x-admin-layout>
    {{--
        Konfiguration übernehmen (Ralf, 2026-10-03, Vorbild InDesign "Stile laden"): Quelle und Ziel wählen,
        dann einzelne Einträge ankreuzen. Gleichnamiges im Ziel ist markiert und wird je Eintrag überschrieben,
        umbenannt oder übersprungen. Gespeichert wird erst mit dem Button, alles in einem Zug (ganz oder gar nicht).
    --}}
    <div class="flex min-h-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white">
        <form method="GET" action="{{ route('admin.voreinstellungen') }}" class="flex shrink-0 flex-wrap items-end gap-4 border-b border-gray-100 p-3" data-no-submit-lock>
            <div>
                <label class="block text-xs text-gray-500" for="preset-source">{{ __('Übernehmen von') }}</label>
                <select id="preset-source" name="source" class="mt-0.5 w-64 rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">{{ __('– Quelle wählen –') }}</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected($source?->id === $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500" for="preset-target">{{ __('Übernehmen nach') }}</label>
                <select id="preset-target" name="target" class="mt-0.5 w-64 rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">{{ __('– Ziel wählen –') }}</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected($target?->id === $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($ready)
                <p class="ml-auto self-end pb-2 text-xs text-gray-400">{{ __('Umbenennen erzeugt automatisch eine Kopie.') }}</p>
            @endif
            @if ($source && $target && $source->id === $target->id)
                <p class="text-xs text-amber-700">{{ __('Quelle und Ziel müssen verschieden sein.') }}</p>
            @endif
        </form>

        @if (! $ready)
            <p class="p-4 text-sm text-gray-500">{{ __('Wählen Sie Quelle und Ziel. Danach können Sie einzelne Voreinstellungen zum Übernehmen ankreuzen. Es wird nichts gelöscht; Gleichnamiges können Sie überschreiben, umbenennen oder überspringen.') }}</p>
        @else
            <form method="POST" action="{{ route('admin.voreinstellungen.apply') }}" class="flex min-h-0 flex-1 flex-col"
                x-data="{ n: 0, count() { this.n = this.$el.querySelectorAll('input[type=checkbox][name^=sel]:checked').length; } }"
                x-init="count()"
                @change="count()">
                @csrf
                <input type="hidden" name="source" value="{{ $source->id }}">
                <input type="hidden" name="target" value="{{ $target->id }}">

                <div class="min-h-0 flex-1 overflow-y-auto p-3">
                    @if ($report)
                        <div class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm">
                            <p class="font-medium text-green-800">{{ __('Übernommen:') }}</p>
                            <ul class="mt-1 space-y-0.5 text-xs text-green-900">
                                @foreach ($report as $line)
                                    <li>{{ $line['area'] }}: <span class="font-medium">{{ $line['label'] }}</span> – {{ $line['outcome'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @foreach ($areas as $area)
                        <section class="mb-8" data-area="{{ $area['key'] }}">
                            @php $previousGroup = null; @endphp
                            <div class="mb-1 flex items-center gap-3 border-b border-gray-200 pb-1">
                                <h3 class="text-sm font-semibold text-gray-800">{{ $area['label'] }}</h3>
                                <span class="text-xs text-gray-400">{{ count($area['items']) }}</span>
                                <button type="button" class="rounded border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-200" onclick="this.closest('section').querySelectorAll('input[type=checkbox]').forEach((c) => c.checked = true); this.closest('form').dispatchEvent(new Event('change', { bubbles: true }))">{{ __('Alle') }}</button>
                                <button type="button" class="rounded border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-200" onclick="this.closest('section').querySelectorAll('input[type=checkbox]').forEach((c) => c.checked = false); this.closest('form').dispatchEvent(new Event('change', { bubbles: true }))">{{ __('Keine') }}</button>
                            </div>
                            @if ($area['items'] === [])
                                <p class="py-2 text-xs text-gray-400">{{ __('Die Quelle hat hier keine Einträge.') }}</p>
                            @endif
                            <ul>
                                @foreach ($area['items'] as $item)
                                    @if (($item['group'] ?? null) && ($item['group'] !== ($previousGroup ?? null)))
                                        <li class="mb-1 mt-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $item['group'] }}</p>
                                            @if (! empty($item['group_hint']))
                                                <p class="text-xs text-gray-400">{{ $item['group_hint'] }}</p>
                                            @endif
                                        </li>
                                    @endif
                                    @php $previousGroup = $item['group'] ?? null; @endphp
                                    <li class="flex items-center gap-3 py-0.5 text-sm" @if (! empty($item['hint'])) title="{{ $item['hint'] }}" @endif @if ($item['parent']) style="padding-left: 1.75rem" @endif>
                                        <label class="flex min-w-0 items-center gap-2">
                                            <input type="checkbox" name="sel[{{ $area['key'] }}][{{ $item['key'] }}]" value="1" class="rounded border-gray-300">
                                            <span class="truncate {{ $item['parent'] ? 'text-gray-700' : 'font-medium text-gray-900' }}">{{ $item['label'] }}</span>
                                            @if ($item['note'])
                                                <span class="shrink-0 text-xs text-gray-400">{{ $item['note'] }}</span>
                                            @endif
                                        </label>
                                        @if ($item['conflict'])
                                            <span class="ml-auto shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800" title="{{ __('Im Ziel gibt es bereits einen Eintrag mit diesem Namen.') }}">{{ __('gibt es schon') }}</span>
                                            <select name="act[{{ $area['key'] }}][{{ $item['key'] }}]" class="w-36 shrink-0 rounded-md border-gray-300 py-0.5 text-xs" title="{{ __('Was soll mit dem vorhandenen Eintrag im Ziel geschehen?') }}">
                                                <option value="copy">{{ __('Überspringen') }}</option>
                                                @if ($item['renamable'])
                                                    <option value="rename">{{ __('Umbenennen') }}</option>
                                                @endif
                                                @if ($item['overwritable'] ?? true)
                                                    <option value="overwrite">{{ __('Überschreiben') }}</option>
                                                @endif
                                            </select>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach

                    <p class="text-xs text-gray-400">{{ __('Wenn Sie eine Unterart wählen, wird ihre Projektart bei Bedarf automatisch mit angelegt.') }}</p>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-gray-100 p-3" x-show="n > 0" x-cloak>
                    <button type="button" @click="$el.closest('form').querySelectorAll('input[type=checkbox]').forEach((c) => c.checked = false); count()" class="whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                    <button type="submit" class="whitespace-nowrap rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </div>
            </form>
        @endif
    </div>
</x-admin-layout>
