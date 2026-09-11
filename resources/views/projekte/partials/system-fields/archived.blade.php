<div>
    <label class="flex items-center gap-1 text-xs text-gray-700">
        <input type="hidden" name="archived" value="0">
        <input type="checkbox" name="archived" value="1" class="rounded border-gray-300" @checked(old('archived', $project->archived))>
        {{ __('Archiviert') }}
    </label>
</div>
