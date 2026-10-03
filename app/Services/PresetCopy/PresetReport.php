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

    /** @var list<callable> */
    private array $deferred = [];

    /** Arbeit, die erst nach dem Commit laufen darf (z.B. Datenbank-Spalten anlegen, das beendet Transaktionen). */
    public function defer(callable $callback): void
    {
        $this->deferred[] = $callback;
    }

    public function runDeferred(): void
    {
        foreach ($this->deferred as $callback) {
            $callback();
        }
        $this->deferred = [];
    }

    /** @return list<array{area: string, label: string, outcome: string}> */
    public function lines(): array
    {
        return $this->lines;
    }
}
