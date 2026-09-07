<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projektpfad wandert vom generischen, mandanten-gescopten Setting
     * direkt auf den Tenant selbst - Ralf: "Die Projektverzeichnisse
     * könnten sich im Terrabyte-Bereich bewegen, da macht es Sinn,
     * verschiedene Server angeben zu können" - pro Kunde ein eigener
     * Pfad, direkt in der Kundenverwaltung editierbar statt über
     * Tenant-Wechsel + Konfig-Seite für jeden Kunden einzeln.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('project_path', 500)->nullable()->after('name');
        });

        DB::table('tenants')->get(['id'])->each(function ($tenant) {
            $value = DB::table('settings')
                ->where('tenant_id', $tenant->id)
                ->where('key', 'project_path')
                ->value('value');

            if ($value !== null && trim($value) !== '') {
                DB::table('tenants')->where('id', $tenant->id)->update(['project_path' => $value]);
            }
        });

        DB::table('settings')->where('key', 'project_path')->delete();
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('project_path');
        });
    }
};
