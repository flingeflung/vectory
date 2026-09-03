<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Ralf',
            'username' => 'ergetr65@googlemail.com',
            'email' => 'ergetr65@googlemail.com',
            'password' => bcrypt('vectory-start'),
        ]);

        // Gemeinsamer Test-User für manuelle Tests (z.B. verschiedene Rollen), bewusst mit einfachem Passwort.
        User::factory()->create([
            'name' => 'QA Test',
            'username' => 'qa-test',
            'email' => 'qa-test@example.invalid',
            'password' => bcrypt('test'),
        ]);

        $this->call(AttributeSeeder::class);
    }
}
