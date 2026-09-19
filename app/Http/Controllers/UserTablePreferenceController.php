<?php

namespace App\Http\Controllers;

use App\Models\UserTablePreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Persönliche Spaltenbreiten (Ralf, 2026-09-19) - gespeichert wird beim
 * Loslassen der Maus, nicht laufend beim Ziehen.
 */
class UserTablePreferenceController extends Controller
{
    public function update(Request $request, string $tableKey): Response
    {
        abort_unless(in_array($tableKey, UserTablePreference::TABLE_KEYS, true), 404);

        $data = $request->validate([
            'widths' => ['required', 'array', 'min:1', 'max:60'],
            // Grenzen bewusst großzügig, aber endlich - eine gespeicherte Breite
            // darf eine Spalte nie unbrauchbar machen.
            'widths.*' => ['integer', 'between:40,1200'],
        ]);

        // Schlüssel prüfen: nur kurze Bezeichner, keine beliebigen Strings speichern.
        $widths = collect($data['widths'])->filter(fn ($width, $key) => preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $key))->all();
        abort_if($widths === [], 422);

        UserTablePreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'table_key' => $tableKey],
            ['column_widths' => $widths],
        );

        return response()->noContent();
    }

    public function destroy(Request $request, string $tableKey): Response
    {
        abort_unless(in_array($tableKey, UserTablePreference::TABLE_KEYS, true), 404);

        UserTablePreference::query()->where('user_id', $request->user()->id)->where('table_key', $tableKey)->delete();

        return response()->noContent();
    }
}
