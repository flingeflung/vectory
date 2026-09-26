<x-guest-layout>
    <h1 class="mb-3 text-lg font-semibold text-gray-900">{{ __('Korrekturen einarbeiten') }}</h1>

    <div class="mb-4 text-sm text-gray-700">
        <div>{{ __('Projekt') }} <strong>{{ $project->source_pn }}</strong> ({{ $project->title }})</div>
        <div>{{ __('Workflow-Schritt') }}: <strong>{{ $step->title }}</strong></div>
    </div>

    @if ($errorMessage)
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ url()->full() }}" enctype="multipart/form-data" class="space-y-3">
        <div>
            <label class="text-xs text-gray-500">{{ __('Korrigierte PDF-Datei') }}</label>
            <input type="file" name="file" accept="application/pdf,.pdf" required class="mt-0.5 block w-full text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500">{{ __('Kommentar (optional)') }}</label>
            <textarea name="comment" rows="3" maxlength="2000" class="mt-0.5 w-full rounded border-gray-300 text-sm">{{ old('comment') }}</textarea>
        </div>
        <button type="submit" class="w-full rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
            {{ __('Hochladen') }}
        </button>
    </form>
</x-guest-layout>
