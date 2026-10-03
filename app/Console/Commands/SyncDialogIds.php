<?php

namespace App\Console\Commands;

use App\Support\DialogId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Trägt neue Dialoge (<x-modal name="…">) mit ihrer berechneten ID in config/dialog-ids.php ein.
 * Bestehende Einträge bleiben unverändert (die ID ist fest, auch wenn ein Name nicht mehr in den
 * Views vorkommt - nach einer Umbenennung dort den neuen Namen mit der alten ID eintragen).
 */
class SyncDialogIds extends Command
{
    protected $signature = 'dialogs:sync';

    protected $description = 'Trägt neue Dialoge mit ihrer Dialog-ID in config/dialog-ids.php ein';

    /** @return array<int, string> */
    public static function modalNamesInViews(): array
    {
        $names = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (preg_match_all('/<x-modal\s+name="([^"]+)"/', $file->getContents(), $matches)) {
                foreach ($matches[1] as $name) {
                    $static = preg_replace('/-\{\{[^}]*\}\}$/', '', $name);
                    if (! str_contains($static, '{{')) {
                        $names[$static] = true;
                    }
                }
            }
        }

        return array_keys($names);
    }

    public function handle(): int
    {
        $path = config_path('dialog-ids.php');
        $current = is_file($path) ? (array) require $path : [];
        $added = [];

        foreach (self::modalNamesInViews() as $name) {
            if (! isset($current[$name])) {
                $current[$name] = DialogId::compute($name);
                $added[] = $name;
            }
        }

        ksort($current);
        $lines = ["<?php", '', '/*', ' * Feste Dialog-IDs (siehe App\\Support\\DialogId). Nach einer Umbenennung eines Dialogs den neuen', ' * Namen mit der ALTEN ID eintragen; neue Dialoge ergänzt `php artisan dialogs:sync`.', ' */', '', 'return ['];
        foreach ($current as $name => $id) {
            $lines[] = "    '".addslashes($name)."' => '".$id."',";
        }
        $lines[] = '];';
        File::put($path, implode("\n", $lines)."\n");

        $this->info(count($added).' neue(r) Dialog(e) eingetragen, '.count($current).' insgesamt.');
        foreach ($added as $name) {
            $this->line('  + '.$name.' => '.$current[$name]);
        }

        return self::SUCCESS;
    }
}
