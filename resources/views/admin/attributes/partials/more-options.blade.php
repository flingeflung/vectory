{{--
    "Weitere Optionen" eines Zusatzfelds (Ralf, 2026-09-21) - eingeklappt direkt über dem Speichern-Button:
    Vorbelegung beim Anlegen, Pflichtfeld, Einheit/Suffix, Änderungen in den Vorgängen protokollieren und
    (nur einzeilige Textfelder) "Beim Aufversionieren hochzählen".

    Zwei Betriebsarten:
    - $attribute gesetzt: bestehendes Feld, der Feldtyp steht fest (Zeile in der Feldliste).
    - $attribute null: neues Feld, der Feldtyp kommt aus dem Alpine-Zustand newType des umgebenden Formulars.
    Pulldowns haben ihre Optionen im eigenen Overlay (siehe index.blade.php), nicht hier.
--}}
@php
    $isNew = $attribute === null;
    $type = $attribute?->data_type;
    $inputClass = 'mt-0.5 rounded-md border-gray-300 py-0.5 text-xs';
@endphp
<details class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600">
    <summary class="cursor-pointer select-none font-medium text-gray-500">{{ __('Weitere Optionen') }}</summary>
    <div class="mt-2 space-y-2">
        {{-- Vorbelegung: passend zum Feldtyp genau EIN Eingabefeld (x-if statt x-show, damit nur eines mitgeschickt wird) --}}
        <div>
            <label class="block text-gray-500">{{ __('Vorbelegung bei neuem Projekt') }}</label>
            @if ($isNew)
                <template x-if="newType === 'text' || newType === 'textarea'">
                    <input type="text" name="default_value" class="{{ $inputClass }} w-48">
                </template>
                <template x-if="newType === 'number'">
                    <input type="number" step="any" name="default_value" class="{{ $inputClass }} w-32">
                </template>
                <template x-if="newType === 'date'">
                    <input type="date" name="default_value" class="{{ $inputClass }}">
                </template>
                <template x-if="newType === 'boolean'">
                    <select name="default_value" class="{{ $inputClass }}">
                        <option value="">{{ __('– keine –') }}</option>
                        <option value="1">{{ __('Ja') }}</option>
                        <option value="0">{{ __('Nein') }}</option>
                    </select>
                </template>
                <template x-if="newType === 'select'">
                    <p class="text-gray-400">{{ __('Bei Pulldowns nach dem Anlegen über „Ändern“ einstellbar.') }}</p>
                </template>
            @elseif ($type === 'number')
                <input type="number" step="any" name="default_value" value="{{ $attribute->default_value }}" class="{{ $inputClass }} w-32">
            @elseif ($type === 'date')
                <input type="date" name="default_value" value="{{ $attribute->default_value }}" class="{{ $inputClass }}">
            @elseif ($type === 'boolean')
                <select name="default_value" class="{{ $inputClass }}">
                    <option value="">{{ __('– keine –') }}</option>
                    <option value="1" @selected($attribute->default_value === '1')>{{ __('Ja') }}</option>
                    <option value="0" @selected($attribute->default_value === '0')>{{ __('Nein') }}</option>
                </select>
            @else
                <input type="text" name="default_value" value="{{ $attribute->default_value }}" class="{{ $inputClass }} w-48">
            @endif
            <p class="mt-0.5 text-gray-400">{{ __('Gilt nur für Projekte, für deren Projektart das Feld gilt (beim Anlegen: Felder, die für alle Projektarten gelten).') }}</p>
        </div>

        @if ($isNew)
            <label x-show="newType !== 'boolean'" x-cloak class="flex items-center gap-1.5">
                <input type="checkbox" name="required" value="1" class="rounded border-gray-300">
                {{ __('Pflichtfeld (muss beim Speichern ausgefüllt sein)') }}
            </label>
            <div x-show="newType === 'text' || newType === 'number'" x-cloak>
                <label class="block text-gray-500">{{ __('Einheit / Suffix hinter dem Wert') }}</label>
                <input type="text" name="unit" maxlength="30" placeholder="{{ __('z. B. Stück, mm') }}" class="{{ $inputClass }} w-32">
            </div>
        @else
            @if ($type !== 'boolean')
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="required" value="1" @checked($attribute->required) class="rounded border-gray-300">
                    {{ __('Pflichtfeld (muss beim Speichern ausgefüllt sein)') }}
                </label>
            @endif
            @if (in_array($type, ['text', 'number'], true))
                <div>
                    <label class="block text-gray-500">{{ __('Einheit / Suffix hinter dem Wert') }}</label>
                    <input type="text" name="unit" maxlength="30" value="{{ $attribute->unit }}" placeholder="{{ __('z. B. Stück, mm') }}" class="{{ $inputClass }} w-32">
                </div>
            @endif
        @endif

        <label class="flex items-center gap-1.5">
            <input type="checkbox" name="log_changes" value="1" @checked(! $isNew && $attribute->log_changes) class="rounded border-gray-300">
            {{ __('Änderungen in den Vorgängen protokollieren') }}
        </label>

        @if ($isNew)
            <label x-show="newType === 'text'" x-cloak class="flex items-center gap-1.5" title="{{ __('Beim Kopieren als neue Version wird die letzte Zahl im Text um 1 erhöht, z. B. V0015 wird zu V0016.') }}">
                <input type="checkbox" name="increments_on_new_version" value="1" class="rounded border-gray-300">
                {{ __('Beim Aufversionieren hochzählen (z. B. Kundenversion)') }}
            </label>
        @elseif ($type === 'text')
            <label class="flex items-center gap-1.5" title="{{ __('Beim Kopieren als neue Version wird die letzte Zahl im Text um 1 erhöht, z. B. V0015 wird zu V0016.') }}">
                <input type="checkbox" name="increments_on_new_version" value="1" @checked($attribute->increments_on_new_version) class="rounded border-gray-300">
                {{ __('Beim Aufversionieren hochzählen (z. B. Kundenversion)') }}
            </label>
        @endif
    </div>
</details>
