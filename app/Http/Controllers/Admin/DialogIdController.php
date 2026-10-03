<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DialogId;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Support-Seite (Ralf, 2026-10-03): Ein Tester nennt die Dialog-ID aus der unteren linken Ecke eines
 * Dialogs - hier lässt sie sich dem Dialog zuordnen (technischer Name, Titel, Quelltext-Datei). Nur Super-Admin (wie die Hilfeverwaltung).
 */
class DialogIdController extends Controller
{
    public function index(): View
    {
        // Dialognamen aus den Views (statisch benannte): Datei und Titel (best effort aus dem Quelltext).
        $sources = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            $content = str_replace("\r\n", "\n", $file->getContents());
            if (! preg_match_all('/<x-modal\s+name="([^"]+)"/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $relative = str_replace(['\\', resource_path('views').'/'], ['/', ''], $file->getPathname());
            $relative = str_replace(str_replace('\\', '/', resource_path('views')).'/', '', $relative);

            foreach ($matches[1] as [$rawName, $offset]) {
                $static = preg_replace('/-\{\{[^}]*\}\}$/', '', $rawName);
                if (str_contains($static, '{{')) {
                    continue;
                }
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                // Titel: erste übersetzte Überschrift kurz nach dem Dialog-Beginn.
                $after = substr($content, $offset, 4000);
                $title = preg_match('/<h[1-3][^>]*>\s*\{\{\s*__\(\'([^\']+)\'\)/', $after, $titleMatch) ? $titleMatch[1] : null;
                $sources[$static] = ['file' => $relative.':'.$line, 'title' => $title];
            }
        }

        $rows = collect((array) config('dialog-ids'))
            ->map(function (string $id, string $name) use ($sources) {
                return [
                    'id' => $id,
                    'name' => $name,
                    'title' => $sources[$name]['title'] ?? null,
                    'file' => $sources[$name]['file'] ?? null,
                    'dynamic' => ! isset($sources[$name]),
                ];
            })
            ->sortBy('id')
            ->values();

        return view('admin.dialog-ids.index', ['rows' => $rows]);
    }
}
