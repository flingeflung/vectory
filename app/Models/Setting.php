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

    public const DEFINITIONS = [
        'project_path' => [
            'label' => 'Projektpfad',
            'description' => 'Basisverzeichnis für Vectory-Projektdateien.',
            'default' => '',
        ],
    ];
}
