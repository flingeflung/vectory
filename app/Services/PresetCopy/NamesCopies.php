<?php

namespace App\Services\PresetCopy;

/** Namensvergabe für "Umbenennen": "Name (Kopie)", bei Bedarf "Name (Kopie 2)" usw. */
trait NamesCopies
{
    /** @param list<string> $taken */
    private function freeName(string $name, array $taken): string
    {
        $candidate = $name.' ('.__('Kopie').')';
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = $name.' ('.__('Kopie').' '.$i.')';
        }

        return $candidate;
    }
}
