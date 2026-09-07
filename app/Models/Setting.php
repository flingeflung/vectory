<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Bekannte Einstellungs-Keys sind fest im Code definiert (Label +
 * Beschreibung + Default), analog zum Rechte-Katalog - Mandanten legen
 * nur die Werte fest, keine eigenen Keys. Neue Einstellungen werden bei
 * Bedarf hier ergänzt, dann erscheinen sie automatisch auf der Konfig-Seite.
 */
#[Fillable(['tenant_id', 'key', 'value'])]
class Setting extends Model
{
    use BelongsToTenant;

    /**
     * Aktuell leer - "Projektpfad" ist auf Tenant.project_path umgezogen
     * (siehe Migration 2026_09_07_103706), weil er pro Kunde statt pro
     * Konfig-Seiten-Aufruf gesetzt werden muss. Bleibt als Mechanismus für
     * künftige, wirklich mandantenweite Einstellungen bestehen.
     */
    public const DEFINITIONS = [];
}
