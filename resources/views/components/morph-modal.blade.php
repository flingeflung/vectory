{{--
    Morphen (Ralf, 2026-10-06): kleines Fenster mit den Rollen, die der Super-Admin testen will. Bei "User" zusätzlich das Rechte-Set.
    Nur für den echten Super-Admin (auch während er gemorpht ist, damit er die Rolle wechseln kann).
--}}
@if (auth()->check() && \App\Support\Morph::isRealSuperAdmin(auth()->user()))
    @php
        $morphTemplates = \App\Models\PermissionTemplate::query()->where('is_baustein', false)->orderBy('sort')->orderBy('name')->get(['id', 'name']);
        $morphState = \App\Support\Morph::state();
    @endphp
    <x-modal name="morph" max-width="md" :draggable="true">
        <div
            x-data="{
                role: {{ \Illuminate\Support\Js::from($morphState['role'] ?? 'organization_admin') }},
                templateId: {{ \Illuminate\Support\Js::from((string) ($morphState['template_id'] ?? '')) }},
                busy: false,
                async start() {
                    this.busy = true;
                    await window.startMorph(this.role, this.role === 'user' && this.templateId ? Number(this.templateId) : null);
                    this.busy = false;
                },
            }"
        >
            <div data-drag-handle class="flex cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Morphen') }}</h3>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'morph' }))" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('Schließen') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="space-y-3 p-4 text-sm">
                <p class="text-xs text-gray-500">{{ __('Wählen Sie die Rolle, deren Sicht Sie prüfen möchten. Ihr Benutzer und Ihre Person bleiben unverändert, nur Rechte und Sichtbarkeit wechseln.') }}</p>
                <div class="space-y-1.5">
                    <label class="flex items-center gap-2"><input type="radio" value="organization_admin" x-model="role" class="border-gray-300"> {{ __('Organisations-Admin') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" value="central_admin" x-model="role" class="border-gray-300"> {{ __('Zentral-Admin') }}</label>
                    <label class="flex items-center gap-2"><input type="radio" value="user" x-model="role" class="border-gray-300"> {{ __('User') }}</label>
                </div>
                <div x-show="role === 'user'" x-cloak>
                    <label class="block text-xs text-gray-500">{{ __('Rechte-Set') }}</label>
                    <select x-model="templateId" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                        <option value="">{{ __('– ohne Rechte-Set –') }}</option>
                        @foreach ($morphTemplates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 p-3">
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'morph' }))" class="whitespace-nowrap rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                <button type="button" @click="start()" :disabled="busy" class="whitespace-nowrap rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-50">{{ __('Morphen') }}</button>
            </div>
        </div>
    </x-modal>
@endif
