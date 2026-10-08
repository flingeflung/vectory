<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konto-Aktivierung per Link (Ralf, 2026-10-08, Vorbild ebiabi85): ein vorbereitetes Konto hat weder Benutzername noch Passwort
 * (Status "pending"), bis die Person den Aktivierungslink nutzt. Der Token wird nur als Hash gespeichert, einmal nutzbar, mit Ablaufzeit.
 * Bestehende Konten gelten als aktiv.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('role');
            $table->timestamp('activated_at')->nullable()->after('email_verified_at');
            $table->string('password')->nullable()->change();
        });
        DB::table('users')->update(['activated_at' => DB::raw('created_at')]);

        Schema::create('account_activation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_activation_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'activated_at']);
        });
    }
};
