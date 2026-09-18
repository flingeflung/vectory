<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: die Artikel, analog zu den Mail-Vorlagen. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.hilfeseiten')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-96 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newArticle: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Hilfeseiten') }}</span>
                <button type="button" @click="newArticle = !newArticle; if (newArticle) $nextTick(() => $refs.newArticleTitle.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newArticle" x-cloak method="POST" action="{{ route('admin.hilfeseiten.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="title" x-ref="newArticleTitle" placeholder="{{ __('Titel (:locale)', ['locale' => \App\Models\HelpArticle::AVAILABLE_LOCALES[\App\Models\HelpArticle::PRIMARY_LOCALE]]) }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </form>

                @if ($tree->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Hilfeseiten angelegt.') }}</div>
                @else
                    @include('admin.help-articles.partials.tree', ['nodes' => $tree, 'parentId' => null, 'selected' => $selected])
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: der gewählte Artikel zum Bearbeiten. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selected)
            <div
                class="flex min-h-0 flex-1 flex-col"
                x-data="{ dirty: false, locale: {{ \Illuminate\Support\Js::from(\App\Models\HelpArticle::PRIMARY_LOCALE) }} }"
            >
                {{-- Speichern- und Lösch-Formular als Geschwister, nicht
                     verschachtelt (gleicher Grund wie bei den Mail-Vorlagen:
                     kein <form> im <form>). --}}
                <form
                    id="help-article-form-{{ $selected->id }}"
                    data-row-form
                    method="POST"
                    action="{{ route('admin.hilfeseiten.update', $selected) }}"
                    class="flex min-h-0 flex-1 flex-col"
                    @input="dirty = window.formIsDirty($el, window.__helpArticlesDirtyForms)"
                    @submit="dirty = false; window.__helpArticlesDirtyForms.delete($el)"
                >
                @csrf
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Seiten (Routennamen), zu denen dieser Artikel automatisch angezeigt wird') }}</label>
                        <input type="text" name="route_names" value="{{ implode(', ', $selected->route_names ?? []) }}" placeholder="{{ __('z. B. admin.kunden') }}" class="mt-0.5 w-full rounded-md border-gray-300 font-mono text-sm">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Sichtbar für') }}</label>
                        <select name="visible_role" class="mt-0.5 w-56 rounded-md border-gray-300 text-sm">
                            <option value="" @selected($selected->visible_role === null)>{{ __('Alle') }}</option>
                            @foreach (\App\Models\HelpArticle::VISIBILITY_LEVELS as $value => $label)
                                <option value="{{ $value }}" @selected($selected->visible_role === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Gilt nur für diese Seite selbst, nicht für ihre Unterseiten.') }}</p>
                    </div>

                    <div class="border-b border-gray-200">
                        <div class="flex gap-3 text-xs">
                            @foreach ($locales as $localeCode => $localeLabel)
                                @php($hasTranslation = $selected->translations->firstWhere('locale', $localeCode) !== null)
                                <button
                                    type="button"
                                    @click="locale = {{ \Illuminate\Support\Js::from($localeCode) }}"
                                    :class="locale === {{ \Illuminate\Support\Js::from($localeCode) }} ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                                    class="pb-1.5"
                                >
                                    {{ $localeLabel }}@unless ($hasTranslation) <span class="text-gray-400">({{ __('leer') }})</span>@endunless
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @foreach ($locales as $localeCode => $localeLabel)
                        @php($t = $selected->translations->firstWhere('locale', $localeCode))
                        <div x-show="locale === {{ \Illuminate\Support\Js::from($localeCode) }}" x-cloak class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('Titel') }}</label>
                                <input type="text" name="translations[{{ $localeCode }}][title]" value="{{ $t?->title }}" @if ($localeCode === \App\Models\HelpArticle::PRIMARY_LOCALE) required @endif class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('Zusätzliche Suchbegriffe (Komma-getrennt, optional)') }}</label>
                                <input type="text" name="translations[{{ $localeCode }}][keywords]" value="{{ $t?->keywords }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('Text (Markdown)') }}</label>
                                <p class="mt-0.5 text-xs text-gray-400">
                                    {{ __('# Überschrift · ## Unterüberschrift · **fett** · *kursiv* · - Punkt (Liste) · 1. Punkt (nummeriert) · [Linktext](https://…) · > Zitat · :button für einen Button/UI-Element wie im Tool', ['button' => '{+Neu}']) }}
                                </p>
                                <textarea name="translations[{{ $localeCode }}][body]" rows="14" class="mt-0.5 w-full rounded-md border-gray-300 font-mono text-sm">{{ $t?->body }}</textarea>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ __('Bild einfügen: Datei nach public/images/hilfe/ legen, dann im Text z. B. :placeholder schreiben - erscheint als eigener Block, Folgetext kommt automatisch darunter. Empfohlene Bildgröße: max. ca. 1200 px breit, unter 500 KB (wird angezeigt verkleinert, bei Klick in Originalgröße).', ['placeholder' => '[screenshot_dashboard1.png]']) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ __('Zu einer anderen Hilfeseite verlinken: :placeholder schreiben, genau der Titel der Zielseite (wie links in der Liste zu sehen) - springt beim Klick direkt dorthin.', ['placeholder' => '[[Kunden klonen]]']) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ __('Zu einer echten Seite im Tool verlinken: :placeholder - der Routenname ist derselbe technische Wert, den dieses Panel zeigt, wenn für eine Seite noch keine Hilfeseite existiert. Kein fest eingetippter Pfad, funktioniert dadurch in jeder Umgebung.', ['placeholder' => '[Zur Kundenverwaltung](route:admin.kunden)']) }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
                </form>

                <form x-ref="deleteForm" method="POST" action="{{ route('admin.hilfeseiten.destroy', $selected) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="shrink-0 flex items-center justify-between border-t border-gray-100 p-3">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, {
                            message: {{ \Illuminate\Support\Js::from(__('Diese Hilfeseite wirklich endgültig löschen?')) }},
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick="window.previewHelpArticle({{ \Illuminate\Support\Js::from($selected->id) }})"
                            class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                        >
                            {{ __('Vorschau') }}
                        </button>
                        <button type="submit" form="help-article-form-{{ $selected->id }}" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Speichern') }}
                        </button>
                    </div>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-4 text-sm text-gray-400">
                {{ __('Wähle links eine Hilfeseite aus, um sie zu bearbeiten.') }}
            </div>
        @endif
    </div>
</div>

{{--
    Vorschau des gerade bearbeiteten (noch nicht gespeicherten) Titels/
    Texts - Ralf: "kurz visuell testen, ohne zu speichern, die echte Hilfe
    aufzurufen, das Stichwort eingeben usw." Bewusst OHNE die
    Baum-Navigation des echten Hilfe-Panels (nicht gebraucht) - gleiche
    Render-Logik (help._article) wie ein echter Artikel, nur mit den
    aktuellen Formularwerten statt der gespeicherten.
--}}
<x-modal name="help-article-preview" max-width="lg" :draggable="true">
    <div class="flex max-h-[80vh] flex-col">
        <div class="flex shrink-0 cursor-move select-none items-center justify-between border-b border-gray-200 bg-gray-100 px-4 py-2" data-drag-handle title="{{ __('Ziehen zum Verschieben') }}">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('Vorschau') }}</h2>
            <button type="button" @click="$dispatch('close-modal', 'help-article-preview')" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('Schließen') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="help-article-preview-body" class="min-h-0 flex-1 overflow-y-auto p-4"></div>
    </div>
</x-modal>

<script>
    window.previewHelpArticle = async function (articleId) {
        const form = document.getElementById('help-article-form-' + articleId);
        const locale = Alpine.$data(form).locale;
        const title = form.querySelector(`[name="translations[${locale}][title]"]`)?.value ?? '';
        const body = form.querySelector(`[name="translations[${locale}][body]"]`)?.value ?? '';

        const html = await fetch({{ \Illuminate\Support\Js::from(route('admin.hilfeseiten.vorschau')) }}, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
            body: JSON.stringify({ title, body }),
        }).then((r) => r.text());

        const previewBody = document.getElementById('help-article-preview-body');
        previewBody.innerHTML = html;
        // Echte Links (Tool-Seiten, extern) sollen die Vorschau nicht
        // verlassen - sonst gehen ungespeicherte Änderungen kommentarlos
        // verloren (Ralf: "das Risiko ist mir zu hoch"). [[Verweise]] auf
        // andere Hilfeseiten (href="#") bleiben unangetastet, die tun in
        // der Vorschau ohnehin nichts.
        previewBody.querySelectorAll('a[href]:not([href="#"])').forEach((link) => {
            link.target = '_blank';
            link.rel = 'noopener';
        });
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'help-article-preview' }));
    };
</script>
