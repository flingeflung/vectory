<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Rechte-Set" / Profil-Muster (siehe Rechtekonzept-Diskussion): jede
     * Person bekommt genau eins zugewiesen, das ihre Rechte vollständig
     * bestimmt. `role` verankert, für welche Ebene (admin/user) ein Set
     * gedacht ist - bestimmt beim Zuweisen an eine Person auch deren
     * User::role. Admin 2 kann sich beliebig viele eigene Sets anlegen
     * (i.d.R. durch Klonen eines vorhandenen und Anpassen).
     */
    public function up(): void
    {
        Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_templates');
    }
};
