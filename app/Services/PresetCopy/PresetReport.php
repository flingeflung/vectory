<?php

namespace App\Services\PresetCopy;

/** Ergebnisbericht einer Übernahme: je Eintrag, was passiert ist. */
class PresetReport
{
    /** @var list<array{area: string, label: string, outcome: string}> */
    private array $lines = [];

    public function add(string $area, string $label, string $outcome): void
    {
        $this->lines[] = ['area' => $area, 'label' => $label, 'outcome' => $outcome];
    }

    /** @return list<array{area: string, label: string, outcome: string}> */
    public function lines(): array
    {
        return $this->lines;
    }
}
