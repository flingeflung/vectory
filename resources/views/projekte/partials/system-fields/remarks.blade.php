<div>
    <label class="block text-xs text-gray-500">{{ __('Bemerkungen') }}</label>
    <textarea name="remarks" rows="2" class="mt-0.5 w-full rounded border-gray-300 text-sm">{{ old('remarks', $project->remarks) }}</textarea>
</div>
