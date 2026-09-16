<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Sammelt alle __()/trans()/trans_choice()-Textliterale aus dem Code und
 * trägt fehlende Schlüssel in lang/{locale}.json ein (leerer Wert) - siehe
 * Backlog "GUI-Texte für Übersetzung ausleiten". Bereits übersetzte
 * Einträge bleiben unangetastet; nicht mehr im Code vorkommende werden
 * NICHT automatisch entfernt (lieber manuell aufräumen, als versehentlich
 * eine fertige Übersetzung zu verlieren, falls ein String nur vorübergehend
 * aus dem Code verschwindet).
 *
 * Die Datei selbst ist bereits der komplette "Export" - kein separates
 * Format nötig: einfach an den Übersetzer geben, die leeren Werte füllen
 * lassen, Datei zurückkopieren. Laravel liest lang/{locale}.json zur
 * Laufzeit automatisch (Fallback auf den Originaltext bei fehlendem
 * Eintrag) - kein zusätzlicher Import-Schritt nötig.
 */
class SyncLangJson extends Command
{
    protected $signature = 'lang:sync {locale=en : Ziel-Sprachcode, z.B. en}';

    protected $description = 'Trägt alle im Code verwendeten Übersetzungs-Strings in lang/{locale}.json ein';

    private const SCAN_DIRS = ['app', 'resources/views'];

    private const FUNCTION_NAMES = ['__', 'trans', 'trans_choice'];

    public function handle(): int
    {
        $locale = $this->argument('locale');
        $path = lang_path("{$locale}.json");

        $existing = File::exists($path) ? (json_decode(File::get($path), true) ?? []) : [];

        $found = $this->extractStrings();

        $added = 0;
        foreach ($found as $string) {
            if (! array_key_exists($string, $existing)) {
                $existing[$string] = '';
                $added++;
            }
        }

        ksort($existing, SORT_STRING);

        File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("{$added} neue(r) String(s) ergänzt, {$path} hat jetzt ".count($existing).' Einträge insgesamt.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function extractStrings(): array
    {
        $pattern = '/\b(?:'.implode('|', self::FUNCTION_NAMES).')\(\s*'
            .'(\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*")/';

        $strings = [];

        foreach (self::SCAN_DIRS as $dir) {
            foreach (File::allFiles(base_path($dir)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = File::get($file->getPathname());

                if (! preg_match_all($pattern, $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $raw) {
                    $strings[] = $this->decodeLiteral($raw);
                }
            }
        }

        return array_values(array_unique($strings));
    }

    private function decodeLiteral(string $raw): string
    {
        $quote = $raw[0];
        $inner = substr($raw, 1, -1);

        if ($quote === "'") {
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $inner);
        }

        return stripcslashes($inner);
    }
}
