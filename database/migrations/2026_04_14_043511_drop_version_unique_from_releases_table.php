<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Usa try/catch para tolerar que el constraint ya haya sido eliminado
     * o nunca haya existido (ej: base de datos en Supabase/PostgreSQL nueva).
     */
    public function up(): void
    {
        // Intentar eliminar el constraint de unicidad simple sobre 'version'.
        // Si no existe (ej: PostgreSQL en Supabase), se ignora el error silenciosamente.
        try {
            Schema::table('releases', function (Blueprint $table) {
                $table->dropUnique(['version']);
            });
        } catch (\Throwable $e) {
            // El constraint no existía; no hay nada que hacer.
        }

        // Crear el nuevo constraint compuesto (version + component) solo si no existe.
        // dropUnique genera el nombre estándar de Laravel: releases_version_component_unique
        $driver = DB::getDriverName();
        $constraintExists = false;

        if ($driver === 'pgsql') {
            $constraintExists = DB::selectOne(
                "SELECT 1 FROM pg_constraint WHERE conname = 'releases_version_component_unique'"
            ) !== null;
        }

        if (!$constraintExists) {
            Schema::table('releases', function (Blueprint $table) {
                $table->unique(['version', 'component']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('releases', function (Blueprint $table) {
                $table->dropUnique(['version', 'component']);
            });
        } catch (\Throwable $e) {
            // El constraint no existía.
        }

        try {
            Schema::table('releases', function (Blueprint $table) {
                $table->unique('version');
            });
        } catch (\Throwable $e) {
            // Ya existía o no aplica.
        }
    }
};
