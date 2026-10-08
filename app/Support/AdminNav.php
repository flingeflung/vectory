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
                ['route' => 'admin.teams', 'match' => 'admin.teams*', 'label' => __('Teams')],
            ],
            __('Projekt-Konfiguration') => [
                ['route' => 'admin.projektkategorien', 'match' => 'admin.projektkategorien*', 'label' => __('Projektkategorien')],
                ['route' => 'admin.projektattribute', 'match' => 'admin.projektattribute*', 'label' => __('Projektattribute')],
                ['route' => 'admin.projektkopie-vorlagen', 'match' => 'admin.projektkopie-vorlagen*', 'label' => __('Projektkopie-Vorlagen')],
                ['route' => 'admin.workflows', 'match' => 'admin.workflows*', 'label' => __('Workflows')],
                ['route' => 'admin.projektschablonen', 'match' => 'admin.projektschablonen*', 'label' => __('Aufwandsprofile')],
                ['route' => 'admin.maerkte', 'match' => 'admin.maerkte*', 'label' => __('Märkte')],
                ['route' => 'admin.jobtypen', 'match' => 'admin.jobtypen*', 'label' => __('Jobtypen (Zeiterfassung)')],
                ['route' => 'admin.checklisten', 'match' => 'admin.checklisten*', 'label' => __('Checklisten')],
                ['route' => 'admin.papierformate', 'match' => 'admin.papierformate*', 'label' => __('Papierformate')],
            ],
            __('Kommunikation') => [
                ['route' => 'admin.mail-vorlagen', 'match' => 'admin.mail-vorlagen*', 'label' => __('Mail-Vorlagen')],
                ['route' => 'admin.hilfeseiten', 'match' => 'admin.hilfeseiten*', 'label' => __('Hilfeseiten'), 'gate' => 'access-superadmin'],
                ['route' => 'admin.begriffe', 'match' => 'admin.begriffe*', 'label' => __('Glossar-Links'), 'gate' => 'access-superadmin'],
                ['route' => 'admin.dialog-ids', 'match' => 'admin.dialog-ids', 'label' => __('Dialog-IDs'), 'gate' => 'access-superadmin'],
            ],
            __('Organisation') => [
                // Stammdaten (Ralf, 2026-10-08): bei mehreren Organisationen die Organisationsseite mit den vier Stammdaten-Knöpfen unter dem Namen, sonst die Konfig-Seite
                SystemSetting::multiTenantEnabled()
                    ? ['route' => 'admin.kunden', 'match' => 'admin.kunden*', 'label' => __('Stammdaten')]
                    : ['route' => 'admin.config', 'match' => 'admin.config', 'label' => __('Stammdaten')],
                ['route' => 'admin.voreinstellungen', 'match' => 'admin.voreinstellungen*', 'label' => __('Konfiguration übernehmen'), 'if' => SystemSetting::multiTenantEnabled(), 'gate' => 'access-central-admin'],
            ],
            __('Planung') => [
                ['route' => 'admin.feiertage', 'match' => 'admin.feiertage*', 'label' => __('Feiertage')],
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

    /**
     * Brotkrumen für den Kopf jeder Admin-Seite (Ralf, 2026-10-03): "Admin › Organisation › Alle Organisationen".
     * Gruppe und Seite kommen aus derselben Definition wie die Navigation selbst.
     *
     * @return array<int, string>
     */
    public static function breadcrumb(): array
    {
        $crumbs = [__('Admin')];

        foreach (self::visibleGroups() as $groupLabel => $items) {
            foreach ($items as $item) {
                if (request()->routeIs($item['match'])) {
                    return [...$crumbs, $groupLabel, $item['label']];
                }
            }
        }

        return $crumbs;
    }
}
