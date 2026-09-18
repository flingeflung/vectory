<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projektschablonen (Nachfolger von Viettos GA-Kategorien, siehe
     * ga_kategorien) - Erfahrungswerte-Katalog für die Redaktionsleitung:
     * Merkmale eines typischen Projekts + aus Erfahrung geschätzte
     * Brutto-Bearbeitungsdauer, Grundlage für die künftige Kapa-Planung
     * (Ralf, 2026-09-18, Step 1 von 4 - siehe Roadmap-Backlog). Bewusst OHNE
     * Verweis auf reale Projekte (Viettos strPN-Freitextfeld) - Schablonen
     * sind Konfiguration/Struktur, keine echten Kundendaten, und sollen
     * genau wie andere Kataloge per TenantConfigCloner auf neue Kunden
     * kopierbar bleiben (Ralfs eigene Abgrenzung dort: nur Struktur, keine
     * echten Daten).
     */
    public function up(): void
    {
        Schema::create('project_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('format');
            $table->unsignedTinyInteger('reusable_content_share');
            $table->unsignedTinyInteger('languages_count');
            $table->unsignedTinyInteger('product_maturity');
            $table->unsignedTinyInteger('product_change_delays');
            $table->unsignedTinyInteger('contact_availability');
            $table->unsignedTinyInteger('localizer_availability');
            $table->unsignedTinyInteger('software_share');
            $table->unsignedTinyInteger('product_complexity');
            $table->unsignedTinyInteger('print_variants_count');
            $table->unsignedTinyInteger('images_count');
            $table->unsignedSmallInteger('duration_value');
            $table->string('duration_unit', 10);
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_templates');
    }
};
