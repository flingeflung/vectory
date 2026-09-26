<x-guest-layout>
    <h1 class="mb-3 text-lg font-semibold text-gray-900">{{ __('Freigabe erteilen') }}</h1>

    <div class="mb-4 text-sm text-gray-700">
        <div>{{ __('Projekt') }} <strong>{{ $project->source_pn }}</strong> ({{ $project->title }})</div>
        <div>{{ __('Workflow-Schritt') }}: <strong>{{ $step->title }}</strong></div>
    </div>

    <form method="POST" action="{{ url()->full() }}">
        <button type="submit" class="w-full rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            {{ __('Freigabe jetzt erteilen') }}
        </button>
    </form>
</x-guest-layout>
