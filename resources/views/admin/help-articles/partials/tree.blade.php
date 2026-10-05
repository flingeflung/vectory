{{--
    Rekursiver Baum-Teil der Hilfeseiten-Verwaltung (Ralf, 2026-09-15: "drei
    Ebenen, per D&D sortier- und ein-/ausrückbar"). $nodes = Geschwister
    einer Ebene (HelpArticle::tree()/->children, je mit 'depth' 1-3
    annotiert), $parentId = ihr gemeinsamer parent_id (für den
    Sortier-Request). Sortieren per Ziehen am Griff (x-sort, wie
    projektkategorien), Ebene wechseln bewusst über eigene Buttons statt per
    Drag (siehe HelpArticleController::indent()/outdent()) - robuster als
    Verschachteln während des Ziehens zu erkennen.
--}}
@if ($nodes->isNotEmpty())
    <div
        x-data="{
            async saveOrder() {
                const ids = [...this.$el.querySelectorAll(':scope > [x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                await fetch({{ \Illuminate\Support\Js::from(route('admin.hilfeseiten.reorder')) }}, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                    body: JSON.stringify({ parent_id: {{ \Illuminate\Support\Js::from($parentId) }}, ids }),
                });
            },
        }"
        x-sort="saveOrder()"
    >
        @foreach ($nodes as $node)
            @php($nodeTitle = $node->translation(\App\Models\HelpArticle::PRIMARY_LOCALE)?->title ?? $node->key)
            <div x-sort:item="{{ $node->id }}">
                <div @contextmenu.prevent="$store.helpTree.openMenu($event, {{ $node->id }}, {{ $node->depth() }})" class="group flex items-center gap-0.5 rounded {{ $selected?->id === $node->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                    @if ($node->children->isNotEmpty())
                        <button type="button" @click="$store.helpTree.toggle({{ $node->id }})" class="flex w-4 shrink-0 items-center justify-center text-gray-400 hover:text-gray-600" :title="$store.helpTree.isCollapsed({{ $node->id }}) ? @js(__('Aufklappen')) : @js(__('Zuklappen'))"><svg class="h-3 w-3 transition-transform" :class="{ '-rotate-90': $store.helpTree.isCollapsed({{ $node->id }}) }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg></button>
                    @else
                        <span class="w-4 shrink-0"></span>
                    @endif
                    <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500 shrink-0" title="{{ __('Verschieben') }}">⠿</span>
                    <div class="flex shrink-0">
                        <form method="POST" action="{{ route('admin.hilfeseiten.ausruecken', $node) }}">
                            @csrf
                            <button type="submit" @if ($node->parent_id === null) disabled @endif title="{{ __('Ausrücken') }}" class="px-0.5 text-gray-300 enabled:hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-30">←</button>
                        </form>
                        <form method="POST" action="{{ route('admin.hilfeseiten.einruecken', $node) }}">
                            @csrf
                            <button type="submit" @if ($loop->first || $node->depth() >= \App\Models\HelpArticle::MAX_DEPTH) disabled @endif title="{{ __('Einrücken') }}" class="px-0.5 text-gray-300 enabled:hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-30">→</button>
                        </form>
                    </div>
                    <a
                        :href="navUrl({ article: {{ $node->id }} })"
                        onclick="return window.navigateOrConfirm(event)"
                        @if ($selected?->id === $node->id) data-selected @endif
                        class="flex min-w-0 flex-1 flex-col py-1 pr-2 {{ $selected?->id === $node->id ? 'font-medium text-indigo-700' : 'text-gray-700' }}"
                    >
                        <span class="truncate">
                            {{ $nodeTitle }}
                            @if ($node->visible_role)
                                <span title="{{ __('Sichtbar für: :level', ['level' => \App\Models\HelpArticle::VISIBILITY_LEVELS[$node->visible_role]]) }}"><x-icons.admin-shield /></span>
                            @endif
                            @if ($node->approved)
                                <span class="text-gray-400" title="{{ __('Freigegeben') }}">🔒</span>
                            @endif
                        </span>
                        @if ($node->orphanedTabKeys())
                            <span class="text-xs font-normal text-red-600" title="{{ __('Dieser Reiter oder Dialog existiert nicht mehr. Die Hilfeseite wird deshalb nicht angezeigt. Bitte den Schlüssel bei „Seiten (Routennamen)“ anpassen.') }}">⚠ {{ __('Schlüssel passt zu keinem Reiter:') }} {{ implode(', ', $node->orphanedTabKeys()) }}</span>
                        @endif
                        @if (empty($node->route_names))
                            <span class="text-xs font-normal text-gray-400">{{ __('kein Seitenbezug') }}</span>
                        @endif
                    </a>
                    <button type="button" @click.stop="$store.helpTree.openMenuAt($el, {{ $node->id }}, {{ $node->depth() }})" class="shrink-0 rounded px-1 text-gray-300 hover:bg-gray-200 hover:text-gray-700" title="{{ __('Neue Seite darunter einfügen') }}">+</button>
                </div>
                <div class="pl-4" x-show="! $store.helpTree.isCollapsed({{ $node->id }})">
                    @include('admin.help-articles.partials.tree', ['nodes' => $node->children, 'parentId' => $node->id, 'selected' => $selected])
                </div>
            </div>
        @endforeach
    </div>
@endif
