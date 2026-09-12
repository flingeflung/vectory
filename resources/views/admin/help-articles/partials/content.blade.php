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
        class="flex w-80 shrink-0 flex-col"
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
                        {{ __('Anlegen') }}
                    </button>
                </form>

                @if ($articles->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Hilfeseiten angelegt.') }}</div>
                @else
                    @foreach ($articles as $article)
                        @php($articleTitle = $article->translation(\App\Models\HelpArticle::PRIMARY_LOCALE)?->title ?? $article->key)
                        <a
                            :href="navUrl({ article: {{ $article->id }} })"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selected?->id === $article->id) data-selected @endif
                            class="flex flex-col rounded px-2 py-1 {{ $selected?->id === $article->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{ $articleTitle }}
                            @if (empty($article->route_names))
                                <span class="text-xs font-normal text-gray-400">{{ __('nur über Suche erreichbar') }}</span>
                            @endif
                        </a>
                    @endforeach
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
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Schlüssel') }}</label>
                            <input type="text" name="key" value="{{ $selected->key }}" required class="mt-0.5 w-full rounded-md border-gray-300 font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Seiten (Routennamen), zu denen dieser Artikel automatisch angezeigt wird') }}</label>
                            <input type="text" name="route_names" value="{{ implode(', ', $selected->route_names ?? []) }}" placeholder="{{ __('z. B. admin.kunden') }}" class="mt-0.5 w-full rounded-md border-gray-300 font-mono text-sm">
                        </div>
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
                                <label class="block text-xs text-gray-500">{{ __('Text (Markdown: # Überschrift, **fett**, - Liste, [Link](url))') }}</label>
                                <textarea name="translations[{{ $localeCode }}][body]" rows="14" class="mt-0.5 w-full rounded-md border-gray-300 font-mono text-sm">{{ $t?->body }}</textarea>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ __('Bild einfügen: Datei nach public/images/hilfe/ legen, dann im Text z. B. :placeholder schreiben - erscheint als eigener Block, Folgetext kommt automatisch darunter. Empfohlene Bildgröße: max. ca. 1200 px breit, unter 500 KB (wird angezeigt verkleinert, bei Klick in Originalgröße).', ['placeholder' => '[screenshot_dashboard1.png]']) }}
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
                    <button type="submit" form="help-article-form-{{ $selected->id }}" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-4 text-sm text-gray-400">
                {{ __('Wähle links eine Hilfeseite aus, um sie zu bearbeiten.') }}
            </div>
        @endif
    </div>
</div>
