<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('config');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        if (Schema::hasTable('dashboard_layouts')) {
            DB::table('dashboard_layouts')->orderBy('id')->each(function ($layout): void {
                DB::table('user_preferences')->insert([
                    'user_id' => $layout->user_id,
                    'key' => 'dashboard',
                    'config' => $layout->config,
                    'created_at' => $layout->created_at,
                    'updated_at' => $layout->updated_at,
                ]);
            });

            Schema::drop('dashboard_layouts');
        }
    }

    public function down(): void
    {
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('config');
            $table->timestamps();

            $table->unique('user_id');
        });

        DB::table('user_preferences')->where('key', 'dashboard')->orderBy('id')->each(function ($preference): void {
            DB::table('dashboard_layouts')->insert([
                'user_id' => $preference->user_id,
                'config' => $preference->config,
                'created_at' => $preference->created_at,
                'updated_at' => $preference->updated_at,
            ]);
        });

        Schema::dropIfExists('user_preferences');
    }
};
