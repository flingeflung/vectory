<x-admin-layout>
    @if (session('status'))
        <div role="status" class="mb-3 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-3 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="flex min-h-0 flex-1 gap-4">
        <div class="flex w-72 shrink-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newGroup: false }">
            <div class="flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Jobgruppen') }}</span>
                <button type="button" @click="newGroup = !newGroup; if (newGroup) $nextTick(() => $refs.newGroupName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">+ {{ __('Neu') }}</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-2 text-sm"
                x-data="{
                    async saveOrder() {
                        const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                        const response = await fetch({{ \Illuminate\Support\Js::from(route('admin.jobtypen.gruppen.reorder')) }}, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                            body: JSON.stringify({ groups: ids }),
                        });
                        if (!response.ok) {
                            await window.notifyDialog({{ \Illuminate\Support\Js::from(__('Sortierung konnte nicht gespeichert werden.')) }});
                            window.location.reload();
                        }
                    },
                }"
                x-sort="saveOrder()"
            >
                <form x-show="newGroup" x-cloak method="POST" action="{{ route('admin.jobtypen.gruppen.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    @csrf
                    <input type="text" name="name" x-ref="newGroupName" required maxlength="255" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs">
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </form>
                @forelse ($groups as $group)
                    <div x-sort:item="{{ $group->id }}" class="flex items-center gap-1 rounded {{ $selectedGroup?->id === $group->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                        <span x-sort:handle class="cursor-move px-1 text-gray-400" title="{{ __('Verschieben') }}">⠿</span>
                        <a href="{{ route('admin.jobtypen', ['gruppe' => $group->id]) }}" onclick="return window.navigateOrConfirm(event)" class="flex flex-1 justify-between px-1 py-1.5 {{ $selectedGroup?->id === $group->id ? 'font-medium text-indigo-700' : 'text-gray-700' }}">
                            <span>{{ $group->name }}</span>
                            <span class="text-xs text-gray-400">{{ $jobs->where('job_group_id', $group->id)->count() }}</span>
                        </a>
                    </div>
                @empty
                    <p class="px-2 py-2 text-gray-500">{{ __('Noch keine Jobgruppen angelegt.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white">
            @if ($selectedGroup)
                <form method="POST" action="{{ route('admin.jobtypen.gruppen.update', $selectedGroup->id) }}" class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-3">
                    @csrf
                    <label class="min-w-48 flex-1 text-xs text-gray-600">{{ __('Jobgruppe') }}
                        <input type="text" name="name" value="{{ $selectedGroup->name }}" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    </label>
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </form>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3" x-data="{ newJob: false }">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-gray-500">{{ __('Jobtypen') }}</h3>
                        <button type="button" @click="newJob = !newJob; if (newJob) $nextTick(() => $refs.newJobCode.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">+ {{ __('Neu') }}</button>
                    </div>
                    <form x-show="newJob" x-cloak method="POST" action="{{ route('admin.jobtypen.store') }}" class="flex flex-wrap items-end gap-2 rounded-md border border-gray-200 p-2">
                        @csrf
                        <input type="hidden" name="job_group_id" value="{{ $selectedGroup->id }}">
                        <label class="w-28 text-xs text-gray-600">{{ __('Kürzel') }}
                            <input type="text" name="code" x-ref="newJobCode" required maxlength="30" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </label>
                        <label class="min-w-48 flex-1 text-xs text-gray-600">{{ __('Langname') }}
                            <input type="text" name="name" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </label>
                        <button type="button" @click="newJob = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                        <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                    </form>
                    @forelse ($jobs->where('job_group_id', $selectedGroup->id) as $job)
                        <form method="POST" action="{{ route('admin.jobtypen.update', $job->id) }}" class="flex flex-wrap items-end gap-2 rounded-md border border-gray-200 p-2">
                            @csrf
                            <label class="w-40 text-xs text-gray-600">{{ __('Jobgruppe') }}
                                <select name="job_group_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    @foreach ($groups as $targetGroup)
                                        <option value="{{ $targetGroup->id }}" @selected($targetGroup->id === $job->job_group_id)>{{ $targetGroup->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="w-28 text-xs text-gray-600">{{ __('Kürzel') }}
                                <input type="text" name="code" value="{{ $job->code }}" required maxlength="30" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="min-w-48 flex-1 text-xs text-gray-600">{{ __('Langname') }}
                                <input type="text" name="name" value="{{ $job->name }}" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                        </form>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('Noch keine Jobtypen in dieser Gruppe angelegt.') }}</p>
                    @endforelse
                </div>
            @else
                <p class="p-6 text-sm text-gray-500">{{ __('Legen Sie zuerst links eine Jobgruppe an.') }}</p>
            @endif
        </div>
    </div>
</x-admin-layout>
