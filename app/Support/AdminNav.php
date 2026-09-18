<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;

/**
 * Zentrale Definition der Admin-Navigation (Gruppen + Seiten je Gruppe) -
 * einzige Quelle für sidebar.blade.php (Gruppen-Ebene, links) und
 * admin-layout.blade.php (Seiten-Ebene der aktiven Gruppe, als Reiter oben).
 * Vorher zwei getrennte Stellen (flache Liste, dann eine Kopie in der
 * admin-layout-eigenen Sidebar) - Ralf: "So sieht meine Admin-Leiste
 * mittlerweile aus. Wir müssen Übersicht reinbringen!" bei 13 Reitern in
 * einer Zeile, danach nochmal verschoben auf "Themen links, Reiter oben"
 * statt einer zweiten eigenen Leiste im Admin-Bereich.
 */
class AdminNav
{
    public static function groups(): array
    {
        return [
            __('Personen & Rechte') => [
                ['route' => 'admin.personen', 'match' => 'admin.personen*', 'label' => __('Personen')],
                ['route' => 'admin.rechte', 'match' => 'admin.rechte', 'label' => __('Rechte')],
                ['route' => 'admin.function-groups', 'match' => 'admin.function-groups', 'label' => __('Funktionsgruppen')],
                ['route' => 'admin.jobtypen', 'match' => 'admin.jobtypen*', 'label' => __('Zeiterfassung')],
            ],
            __('Projekt-Konfiguration') => [
                ['route' => 'admin.projektkategorien', 'match' => 'admin.projektkategorien*', 'label' => __('Projektkategorien')],
                ['route' => 'admin.projektschablonen', 'match' => 'admin.projektschablonen*', 'label' => __('Projektschablonen')],
                ['route' => 'admin.projektattribute', 'match' => 'admin.projektattribute*', 'label' => __('Projektattribute')],
                ['route' => 'admin.projektkopie-vorlagen', 'match' => 'admin.projektkopie-vorlagen*', 'label' => __('Projektkopie-Vorlagen')],
                ['route' => 'admin.maerkte', 'match' => 'admin.maerkte*', 'label' => __('Märkte')],
                ['route' => 'admin.workflows', 'match' => 'admin.workflows*', 'label' => __('Workflows')],
                ['route' => 'admin.checklisten', 'match' => 'admin.checklisten*', 'label' => __('Checklisten')],
                ['route' => 'admin.papierformate', 'match' => 'admin.papierformate*', 'label' => __('Papierformate')],
            ],
            __('Kommunikation') => [
                ['route' => 'admin.mail-vorlagen', 'match' => 'admin.mail-vorlagen*', 'label' => __('Mail-Vorlagen')],
                ['route' => 'admin.hilfeseiten', 'match' => 'admin.hilfeseiten*', 'label' => __('Hilfeseiten'), 'gate' => 'access-superadmin'],
            ],
            __('Mandant') => [
                ['route' => 'admin.config', 'match' => 'admin.config', 'label' => __('Stammdaten')],
                ['route' => 'admin.kunden', 'match' => 'admin.kunden*', 'label' => __('Kunden'), 'if' => SystemSetting::multiTenantEnabled()],
                ['route' => 'admin.superadmin', 'match' => 'admin.superadmin', 'label' => __('Superadmin'), 'gate' => 'access-superadmin'],
            ],
        ];
    }

    /**
     * @return Collection<string, Collection>
     */
    public static function visibleGroups(): Collection
    {
        return collect(self::groups())
            ->map(fn ($items) => collect($items)->filter(
                fn ($item) => (! isset($item['if']) || $item['if']) && (! isset($item['gate']) || auth()->user()->can($item['gate']))
            ))
            ->filter(fn ($items) => $items->isNotEmpty());
    }

    public static function currentGroupLabel(): ?string
    {
        foreach (self::visibleGroups() as $label => $items) {
            foreach ($items as $item) {
                if (request()->routeIs($item['match'])) {
                    return $label;
                }
            }
        }

        return null;
    }
}
