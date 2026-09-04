@php
    $step = $projectWorkflowStep->workflowStep;
@endphp

<div class="mb-3 text-xs text-gray-500">{{ __('Projekt') }} {{ $project->source_pn }}</div>

<div class="mb-3">
    <div class="text-xs text-gray-500">{{ __('Workflow-Schritt') }}</div>
    <div class="font-medium text-gray-900">{{ $step->title }}</div>
</div>

<form
    x-data="{ sendEmail: {{ $step->send_email ? 'true' : 'false' }} }"
    method="POST"
    action="{{ route('projekte.workflow-steps.activate', [$project, $projectWorkflowStep]) }}"
    class="space-y-3"
>
    @csrf

    <label class="flex items-center gap-1.5 text-gray-700">
        <input type="checkbox" name="send_email" value="1" x-model="sendEmail" class="rounded border-gray-300">
        {{ __('E-Mail an Zuständige senden') }}
    </label>

    <div x-show="sendEmail" x-cloak class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-3">
        <div>
            <div class="text-xs text-gray-500">{{ __('Empfänger') }}</div>
            @forelse ($recipients as $recipient)
                <div class="{{ $recipient->active ? 'text-gray-700' : 'text-gray-400' }}">
                    {{ $recipient->fullName() }}{{ ! $recipient->active ? ' [i]' : '' }}{{ ! $recipient->email ? ' ('.__('keine E-Mail hinterlegt').')' : '' }}
                </div>
            @empty
                <div class="text-amber-600">{{ __('Keine Person für diesen Schritt zugewiesen!') }}</div>
            @endforelse
        </div>

        <label class="flex items-center gap-1.5 text-gray-700">
            <input type="checkbox" name="send_copy_to_self" value="1" class="rounded border-gray-300">
            {{ __('Kopie an mich') }}
        </label>

        <div>
            <label class="text-xs text-gray-500">{{ __('Persönliche Nachricht (optional)') }}</label>
            <textarea name="message" rows="3" class="mt-0.5 w-full rounded border-gray-300 text-sm"></textarea>
        </div>
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'activate-workflow-step' }))"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
        >
            {{ __('Abbrechen') }}
        </button>
        <button type="submit" class="rounded bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700">
            {{ __('Schritt aktivieren') }}
        </button>
    </div>
</form>
