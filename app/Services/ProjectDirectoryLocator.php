<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tenant;

/**
 * Findet/erstellt/listet das Dateisystem-Verzeichnis eines Projekts, analog
 * zu Viettos get_real_dirname()/get_pnpfad()/create_pndirs() (siehe
 * D:\htdocs\vietto\includes\incl_functions.php). Ordnername = "<PN>_<sani-
 * tisierter Titel>" unter dem Projektpfad des jeweiligen Mandanten
 * (Tenant.project_path - bewusst direkt am Mandanten statt im generischen
 * Setting-Store, siehe Ralf: "Die Projektverzeichnisse könnten sich im
 * Terrabyte-Bereich bewegen, da macht es Sinn, verschiedene Server pro
 * Kunde angeben zu können"), archivierte Projekte liegen zusätzlich
 * unterhalb von "_Archiv". Die PN-Zuordnung wird NICHT in der DB
 * gespeichert, sondern - wie in Vietto - live per Verzeichnis-Präfix
 * (erste 6 Zeichen) ermittelt, damit ein manuell umbenannter Ordner nicht
 * die Verknüpfung verliert.
 */
class ProjectDirectoryLocator
{
    /**
     * Feste Unterordner-Struktur, 1:1 aus Viettos $pfad_unterverzeichnisse
     * übernommen (incl_functions.php, Zeile ~72-96).
     */
    private const SUBDIRECTORIES = [
        '01_Kommunikation',
        '02_Recherche',
        '03_Grafikerstellung',
        '03_Grafikerstellung/01_Vorlagen',
        '03_Grafikerstellung/01_Vorlagen/STEP-Daten',
        '03_Grafikerstellung/02_Arbeitsdateien',
        '03_Grafikerstellung/03_Fertige_Grafikdaten',
        '04_Arbeitsdateien',
        '05_Lektorat',
        '05_Lektorat/01_Pruefung_durch_PT-PM',
        '05_Lektorat/01_Pruefung_durch_PT-PM/_Archiv',
        '05_Lektorat/02_Korrektur_fuer_Redaktion',
        '05_Lektorat/02_Korrektur_fuer_Redaktion/_Archiv',
        '05_Lektorat/03_Freigabe_durch_Produkttechnik',
        '05_Lektorat/04_Freigabe_durch_Redaktion',
        '05_Lektorat/05_Layoutcheck',
        '06_Uebersetzung',
        '06_Uebersetzung/01_Zur_Uebersetzung',
        '06_Uebersetzung/02_Aus_Uebersetzung_zurueck',
        '06_Uebersetzung/03_Zur_Lokalisierung',
        '06_Uebersetzung/04_Aus_Lokalisierung_zurueck',
        '07_Fertige_Daten',
        '99_Preview',
    ];

    private const ARCHIVE_DIRNAME = '_Archiv';

    public function basePath(int $tenantId): ?string
    {
        $value = Tenant::query()->where('id', $tenantId)->value('project_path');

        return $value !== null && trim($value) !== '' ? rtrim($value, '\\/') : null;
    }

    /**
     * Basispfad des lokalen Arbeitsverzeichnisses (Ralf, 2026-09-26) -
     * eigener, unabhängiger Pfad je Mandant, gleiche Unterordner-Struktur
     * wie das gesperrte Projektverzeichnis oben, aber für externen/
     * Netzlaufwerk-Zugriff gedacht (z.B. Korrektur-Uploads externer
     * Projektbeteiligter). Alle anderen Methoden dieser Klasse (buildIndex,
     * listContents, create, ...) sind bereits basispfad-unabhängig und
     * funktionieren hier genauso.
     */
    public function arbeitsverzeichnisBasePath(int $tenantId): ?string
    {
        $value = Tenant::query()->where('id', $tenantId)->value('arbeitsverzeichnis_path');

        return $value !== null && trim($value) !== '' ? rtrim($value, '\\/') : null;
    }

    /**
     * Projektordner im Arbeitsverzeichnis (per PN-Präfix, wie im gesperrten
     * Projektverzeichnis) - null, wenn nicht konfiguriert, nicht
     * erreichbar oder nicht (eindeutig) auffindbar.
     */
    public function arbeitsverzeichnisProjectPath(Project $project): ?string
    {
        $basePath = $this->arbeitsverzeichnisBasePath($project->tenant_id);

        if ($basePath === null || ! is_dir($basePath)) {
            return null;
        }

        return $this->statusFromIndex($this->buildIndex($basePath), $project->source_pn)['path'];
    }

    /**
     * Löst einen im Freigabe-Request gespeicherten, zum Projektordner
     * RELATIVEN Pfad (Schrägstriche) zu einem absoluten auf - null bei
     * Pfaden, die aus dem Projektordner herausführen würden. Der Pfad
     * kommt zwar aus unserer eigenen DB, das externe Upload-Ziel aber
     * ohne Login angesprochen wird, deshalb hier sicherheitshalber
     * doppelt geprüft.
     */
    public function resolveRelative(string $projectPath, string $relativePath): ?string
    {
        $segments = array_values(array_filter(preg_split('#[\\\\/]+#', $relativePath), fn ($s) => $s !== ''));

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $projectPath.($segments ? DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments) : '');
    }

    /**
     * Flache, eingerückte Liste des Verzeichnisbaums für die Auswahlfelder
     * im Auslösen-Dialog (bewusst zwei einfache Selects statt Baum-
     * Widget, Ralf: PM-/TR-Seite minimal halten).
     *
     * @return list<array{path: string, label: string, type: string}> path = relativ zum Projektordner, Schrägstriche
     */
    public function flatOptions(string $projectPath, bool $dirsOnly = false): array
    {
        $options = [];
        $walk = function (array $entries, string $prefix, int $depth) use (&$walk, &$options, $dirsOnly) {
            foreach ($entries as $entry) {
                if ($entry['type'] === 'file' && $dirsOnly) {
                    continue;
                }

                $relative = $prefix === '' ? $entry['name'] : $prefix.'/'.$entry['name'];
                $options[] = [
                    'path' => $relative,
                    'label' => str_repeat("\u{2003}", $depth).$entry['name'].($entry['type'] === 'dir' ? '/' : ''),
                    'type' => $entry['type'],
                ];

                if ($entry['type'] === 'dir') {
                    $walk($entry['children'] ?? [], $relative, $depth + 1);
                }
            }
        };
        $walk($this->listContents($projectPath), '', 0);

        return $options;
    }

    /**
     * Ein Scan des Basisverzeichnisses (+ _Archiv) reicht für eine ganze
     * Projektliste (25 Zeilen) - deutlich günstiger als Viettos Ansatz,
     * der pro Tabellenzeile einzeln scandir() aufruft.
     *
     * @return array<string, list<array{folder: string, path: string, archived: bool}>> PN (6-stellig) => Treffer
     */
    public function buildIndex(string $basePath): array
    {
        $index = [];

        if (! is_dir($basePath)) {
            return $index;
        }

        $this->scanInto($index, $basePath, false);

        $archivePath = $basePath.DIRECTORY_SEPARATOR.self::ARCHIVE_DIRNAME;
        if (is_dir($archivePath)) {
            $this->scanInto($index, $archivePath, true);
        }

        return $index;
    }

    /**
     * @param  array<string, list<array{folder: string, path: string, archived: bool}>>  $index
     * @return array{status: string, path: ?string, archived: bool}
     */
    public function statusFromIndex(array $index, string $pn): array
    {
        $matches = $index[$pn] ?? [];

        return match (count($matches)) {
            0 => ['status' => 'not_found', 'path' => null, 'archived' => false],
            1 => ['status' => 'found', 'path' => $matches[0]['path'], 'archived' => $matches[0]['archived']],
            default => ['status' => 'ambiguous', 'path' => null, 'archived' => false],
        };
    }

    /**
     * Für eine Projektliste (z.B. Projektübersicht-Seite): Basisverzeichnis
     * + Index nur EINMAL scannen statt pro Zeile, siehe buildIndex().
     *
     * @param  iterable<Project>  $projects
     * @return array<int, array{status: string, path: ?string, archived: bool}> Projekt-ID => Status
     */
    public function statusesForProjects(iterable $projects, int $tenantId): array
    {
        $basePath = $this->basePath($tenantId);

        if ($basePath === null) {
            return collect($projects)->mapWithKeys(fn (Project $p) => [$p->id => ['status' => 'not_configured', 'path' => null, 'archived' => false]])->all();
        }

        if (! is_dir($basePath)) {
            return collect($projects)->mapWithKeys(fn (Project $p) => [$p->id => ['status' => 'unreachable', 'path' => null, 'archived' => false]])->all();
        }

        $index = $this->buildIndex($basePath);

        return collect($projects)->mapWithKeys(fn (Project $p) => [$p->id => $this->statusFromIndex($index, $p->source_pn)])->all();
    }

    /**
     * @return array{status: string, path: ?string, archived: bool}
     */
    public function statusForProject(Project $project): array
    {
        $basePath = $this->basePath($project->tenant_id);

        if ($basePath === null) {
            return ['status' => 'not_configured', 'path' => null, 'archived' => false];
        }

        if (! is_dir($basePath)) {
            return ['status' => 'unreachable', 'path' => null, 'archived' => false];
        }

        return $this->statusFromIndex($this->buildIndex($basePath), $project->source_pn);
    }

    /**
     * Rekursiver Verzeichnisbaum für die "Verzeichnis auflisten"-Ansicht -
     * rein lesend, keine anklickbaren file://-Links (die sind in modernen
     * Browsern unzuverlässig, siehe Ordner-öffnen-Button/Zwischenablage
     * stattdessen).
     *
     * @return list<array{name: string, type: string, size: ?int, children: ?array}>
     */
    public function listContents(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $entries = scandir($path) ?: [];
        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

        $result = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || strtolower($entry) === 'thumbs.db') {
                continue;
            }

            $fullPath = $path.DIRECTORY_SEPARATOR.$entry;
            if (is_dir($fullPath)) {
                $result[] = ['name' => $entry, 'type' => 'dir', 'size' => null, 'children' => $this->listContents($fullPath)];
            } else {
                $result[] = ['name' => $entry, 'type' => 'file', 'size' => filesize($fullPath) ?: 0, 'children' => null];
            }
        }

        return $result;
    }

    /**
     * @return string sanitierter Ordnername (ohne PN-Präfix)
     */
    public function sanitizeFolderName(string $name): string
    {
        $replacements = [
            ',' => '_', '.' => '_', ':' => '_', '?' => '_', '\\' => '_', '/' => '_',
            '"' => '_', "'" => '_', ' ' => '_', '&' => '_und_', '%' => 'Prozent',
            '{' => '_', '}' => '_', '[' => '_', ']' => '_', '<' => '_', '>' => '_',
            '*' => '_', '|' => '_',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        ];

        $sanitized = trim(strtr($name, $replacements));

        return trim(preg_replace('/_+/', '_', $sanitized), '_');
    }

    /**
     * Dateityp-Icon (aus Vietto übernommen, siehe images/i_<extension>.png
     * dort bzw. public/images/filetypes/ hier) - "i_dunno.png" als Fallback
     * für unbekannte Endungen, genau wie in Viettos ajax_get_dircontent.php.
     */
    public static function fileTypeIcon(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        foreach (array_unique([$extension, strtolower($extension)]) as $candidate) {
            if ($candidate !== '' && file_exists(public_path("images/filetypes/i_{$candidate}.png"))) {
                return "images/filetypes/i_{$candidate}.png";
            }
        }

        return 'images/filetypes/i_dunno.png';
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = max($bytes, 0);

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $value : number_format($value, 1)).' '.$unit;
            }

            $value /= 1024;
        }

        return $bytes.' B';
    }

    public function suggestedFolderName(Project $project): string
    {
        return $project->source_pn.'_'.$this->sanitizeFolderName($project->title);
    }

    /**
     * Legt den Projektordner + die feste Unterordner-Struktur an - nur für
     * ein bestehendes Projekt, das noch keins hat (siehe Ralfs Entscheidung:
     * "Projekt neu anlegen" selbst ist noch nicht Teil dieses Features).
     */
    public function create(string $basePath, string $folderName): string
    {
        $absolutePath = $basePath.DIRECTORY_SEPARATOR.$folderName;

        if (! is_dir($absolutePath) && ! mkdir($absolutePath, 0777, true)) {
            throw new \RuntimeException("Verzeichnis konnte nicht angelegt werden: $absolutePath");
        }

        foreach (self::SUBDIRECTORIES as $subdirectory) {
            $path = $absolutePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subdirectory);
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        return $absolutePath;
    }

    private function scanInto(array &$index, string $dir, bool $archived): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || ! is_dir($dir.DIRECTORY_SEPARATOR.$entry)) {
                continue;
            }

            $pn = substr($entry, 0, 6);
            if (! ctype_digit($pn)) {
                continue;
            }

            $index[$pn][] = ['folder' => $entry, 'path' => $dir.DIRECTORY_SEPARATOR.$entry, 'archived' => $archived];
        }
    }
}
