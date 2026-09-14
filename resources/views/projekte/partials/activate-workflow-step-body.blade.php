@php
    $step = $projectWorkflowStep->workflowStep;
    // Ralf-Bug-Report, 2026-09-14: "keine E-Mail hinterlegt" ging im
    // grauen Fließtext unter - er dachte, an den PM sei eine Mail raus,
    // obwohl der Versand mangels Adresse lautlos nichts bewirkt hätte.
    // Wenn NIEMAND der Zuständigen erreichbar ist, ist der Versand
    // komplett wirkungslos - Häkchen dann automatisch aus und gesperrt,
    // statt eine funktionslose Option anzubieten.
    $canSendEmail = $recipients->contains(fn ($recipient) => ! empty($recipient->email));
@endphp

<div class="mb-3 text-xs text-gray-500">{{ __('Projekt') }} {{ $project->source_pn }}</div>

<div class="mb-3">
    <div class="text-xs text-gray-500">{{ __('Workflow-Schritt') }}</div>
    <div class="font-medium text-gray-900">{{ $step->title }}</div>
</div>

<form
    x-data="{ sendEmail: {{ $canSendEmail && $step->send_email ? 'true' : 'false' }} }"
    method="POST"
    action="{{ route('projekte.workflow-steps.activate', [$project, $projectWorkflowStep]) }}"
    class="space-y-3"
>
    @csrf

    <div>
        <label class="flex items-center gap-1.5 text-gray-700">
            <input type="checkbox" name="send_email" value="1" x-model="sendEmail" {{ $canSendEmail ? '' : 'disabled' }} class="rounded border-gray-300 disabled:opacity-50">
            {{ __('E-Mail an Zuständige senden') }}
        </label>
        @unless ($canSendEmail)
            <div class="mt-0.5 text-xs text-amber-600">{{ __('Für niemanden der Zuständigen ist eine E-Mail-Adresse hinterlegt - Versand nicht möglich.') }}</div>
        @endunless
    </div>

    {{--
        Empfänger-Liste bewusst IMMER sichtbar, nicht nur bei
        angehaktem sendEmail - genau das Weglassen hier hätte den
        Warnhinweis oben wieder unsichtbar gemacht, sobald das Häkchen
        (automatisch oder manuell) aus ist.
    --}}
    <div class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-3">
        <div>
            <div class="text-xs text-gray-500">{{ __('Empfänger') }}</div>
            @forelse ($recipients as $recipient)
                <div class="{{ $recipient->active ? 'text-gray-700' : 'text-gray-400' }}">
                    {{ $recipient->fullName() }}{{ ! $recipient->active ? ' [i]' : '' }} <x-absence-icon :person="$recipient" />
                    @unless ($recipient->email)
                        <span class="font-medium text-amber-600">({{ __('keine E-Mail hinterlegt') }})</span>
                    @endunless
                </div>
            @empty
                <div class="text-amber-600">{{ __('Keine Person für diesen Schritt zugewiesen!') }}</div>
            @endforelse
        </div>

        <div x-show="sendEmail" x-cloak class="space-y-3">
            <label class="flex items-center gap-1.5 text-gray-700">
                <input type="checkbox" name="send_copy_to_self" value="1" class="rounded border-gray-300">
                {{ __('Kopie an mich') }}
            </label>

            <div>
                <label class="text-xs text-gray-500">{{ __('Persönliche Nachricht (optional)') }}</label>
                <textarea name="message" rows="3" class="mt-0.5 w-full rounded border-gray-300 text-sm"></textarea>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2 border-t border-gray-200 pt-3">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'activate-workflow-step' }))"
            class="rounded border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
        >
            {{ __('Abbrechen') }}
        </button>
        <button type="submit" class="rounded bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
            {{ __('Schritt aktivieren') }}
        </button>
    </div>
</form>
