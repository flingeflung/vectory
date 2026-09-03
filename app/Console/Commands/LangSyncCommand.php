<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Durchsucht den Code nach __()/@lang()-Aufrufen (Quelltext = Deutsch) und
 * pflegt daraus eine lang/{locale}.json je Zielsprache. Diese Datei ist die
 * Übergabe-/Rückgabedatei für die Übersetzung in Across.
 */
class LangSyncCommand extends Command
{
    protected $signature = 'lang:sync {locale : Zielsprache, z.B. en}';

    protected $description = 'UI-Texte aus dem Code extrahieren und lang/{locale}.json für die Übersetzung (z.B. in Across) aktualisieren';

    private const SCAN_PATHS = ['resources/views', 'app'];

    private const SCAN_EXTENSION = 'php';

    public function handle(): int
    {
        $locale = $this->argument('locale');

        $sourceStrings = $this->extractSourceStrings();

        $langFile = lang_path("{$locale}.json");
        $existing = File::exists($langFile)
            ? json_decode(File::get($langFile), true, flags: JSON_THROW_ON_ERROR)
            : [];

        $new = 0;
        $kept = 0;
        $result = [];

        foreach ($sourceStrings as $string) {
            if (array_key_exists($string, $existing)) {
                $result[$string] = $existing[$string];
                $kept++;
            } else {
                $result[$string] = '';
                $new++;
            }
        }

        $orphaned = array_diff(array_keys($existing), $sourceStrings);

        ksort($result);

        File::ensureDirectoryExists(lang_path());
        File::put(
            $langFile,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->info("lang/{$locale}.json aktualisiert: {$kept} bestehend, {$new} neu, ".count($result)." gesamt.");

        if (count($orphaned) > 0) {
            $this->warn(count($orphaned).' nicht mehr verwendete(r) String(s) noch in der Datei (nicht automatisch entfernt):');
            foreach ($orphaned as $string) {
                $this->line("  - {$string}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function extractSourceStrings(): array
    {
        $strings = [];

        foreach (self::SCAN_PATHS as $path) {
            $fullPath = base_path($path);

            if (! File::isDirectory($fullPath)) {
                continue;
            }

            foreach (File::allFiles($fullPath) as $file) {
                if ($file->getExtension() !== self::SCAN_EXTENSION && ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = File::get($file->getPathname());

                foreach ($this->matchTranslationCalls($contents) as $match) {
                    $strings[$match] = true;
                }
            }
        }

        $strings = array_keys($strings);
        sort($strings);

        return $strings;
    }

    /**
     * @return list<string>
     */
    private function matchTranslationCalls(string $contents): array
    {
        // Erfasst Aufrufe der Übersetzungsfunktion mit einem String-Literal als erstem Argument, inkl. escapter Quotes.
        $pattern = '/(?:__|@lang)\(\s*(\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';

        preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER);

        $result = [];

        foreach ($matches as $match) {
            $raw = $match[2] !== '' ? $match[2] : $match[3];
            $quote = str_starts_with($match[1], "'") ? "'" : '"';
            $unescaped = $quote === "'"
                ? str_replace(["\\'", '\\\\'], ["'", '\\'], $raw)
                : str_replace(['\\"', '\\\\'], ['"', '\\'], $raw);

            // 'datei.key'-Notation (z.B. "auth.password") adressiert Laravels
            // array-basierte lang/{locale}/*.php-Dateien, nicht diesen JSON-Mechanismus.
            if (preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/i', $unescaped) === 1) {
                continue;
            }

            $result[] = $unescaped;
        }

        return $result;
    }
}
