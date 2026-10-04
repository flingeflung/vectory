<x-admin-layout>
    {{--
        Dialog-IDs nachschlagen (Ralf, 2026-10-03): Ein Tester nennt die kleine ID unten links in einem
        Dialog, hier steht, welcher Dialog das ist. Die Suche filtert sofort (Tippen), akzeptiert die ID
        mit oder ohne "D-", Groß- oder Kleinschreibung und sucht außerdem in Name, Titel und Datei.
    --}}
    <div
        class="flex min-h-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white"
        x-data="{
            q: '',
            rows: {{ \Illuminate\Support\Js::from($rows) }},
            norm(value) { return String(value || '').toLowerCase().replace(/\s+/g, ''); },
            matches(row) {
                const needle = this.norm(this.q).replace(/^d-/, '');
                if (needle === '') return true;
                return [row.id.replace(/^D-/, ''), row.name, row.title, row.file, ...(row.tabs || []).flatMap((tab) => [tab.key, tab.label])].some((field) => this.norm(field).includes(needle));
            },
            get shown() { return this.rows.filter((row) => this.matches(row)); },
        }"
        x-init="$nextTick(() => $refs.q.focus())"
    >
        <div class="shrink-0 border-b border-gray-100 p-3">
            <label class="block text-xs text-gray-500">{{ __('Dialog-ID, Name oder Titel') }}</label>
            <div class="mt-0.5 flex items-center gap-3">
                <input
                    type="text"
                    x-ref="q"
                    x-model="q"
                    placeholder="{{ __('z. B. D-78FH') }}"
                    autocomplete="off"
                    class="w-64 rounded-md border-gray-300 font-mono text-sm"
                >
                <span class="text-xs text-gray-400" x-text="shown.length + ' / ' + rows.length"></span>
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ __('Die ID steht klein unten links in jedem Dialog; ein Klick darauf kopiert sie.') }}</p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-1.5 text-left font-medium">{{ __('ID') }}</th>
                        <th class="px-3 py-1.5 text-left font-medium">{{ __('Dialog') }}</th>
                        <th class="px-3 py-1.5 text-left font-medium">{{ __('Titel') }}</th>
                        <th class="px-3 py-1.5 text-left font-medium">{{ __('Quelltext') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="row in shown" :key="row.id + row.name">
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-1.5 font-mono font-semibold text-gray-900">
                                <span x-text="row.id"></span>
                                <button type="button" class="ml-1 rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="{{ __('ID kopieren') }}" @click="window.copyToClipboard(row.id).then((ok) => window.showToast && window.showToast(ok ? row.id + ' ' + {{ \Illuminate\Support\Js::from(__('kopiert')) }} : row.id))">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                </button>
                            </td>
                            <td class="px-3 py-1.5 font-mono text-xs text-gray-700" x-text="row.name"></td>
                            <td class="px-3 py-1.5 text-gray-700">
                                <span x-text="row.title || '–'"></span>
                                <template x-if="row.tabs && row.tabs.length">
                                    <div class="mt-1 space-y-0.5 text-xs text-gray-500">
                                        <div class="text-gray-400">{{ __('Reiter mit eigener Hilfeseite möglich (Schlüssel bei „Seiten (Routennamen)“ eintragen):') }}</div>
                                        <template x-for="tab in row.tabs" :key="tab.key">
                                            <div class="flex items-center gap-2"><code class="select-all font-mono text-gray-700" x-text="tab.key"></code><span x-text="tab.label"></span></div>
                                        </template>
                                    </div>
                                </template>
                            </td>
                            <td class="px-3 py-1.5 font-mono text-xs text-gray-500">
                                <span x-show="row.file" x-text="row.file"></span>
                                <span x-show="!row.file" class="text-gray-400" title="{{ __('Name wird im Quelltext zusammengesetzt (z. B. mit Datensatz-Nummer)') }}">{{ __('dynamisch benannt') }}</span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="shown.length === 0">
                        <td colspan="4" class="px-3 py-4 text-center text-gray-400">{{ __('Keine Dialog-ID gefunden.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
