<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migra el ENUM de 'plan' de (basic, pro, enterprise) a (basico, premium).
     * Soporta PostgreSQL (Supabase), MySQL, MariaDB y SQLite.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Eliminar restricciones previas dependiendo del motor (PostgreSQL o MySQL)
        if ($driver === 'pgsql') {
            // En PostgreSQL, Laravel puede haber creado un constraint de validación
            DB::statement("ALTER TABLE licenses DROP CONSTRAINT IF EXISTS licenses_plan_check");
            // También la versión con el nombre original por si existe en Supabase (antes de renombrar la columna)
            DB::statement("ALTER TABLE licenses DROP CONSTRAINT IF EXISTS licenses_plan_type_check");
            // Pasamos a VARCHAR para permitir la migración sin problemas de tipo ENUM estricto
            DB::statement("ALTER TABLE licenses ALTER COLUMN plan TYPE VARCHAR(50)");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE licenses MODIFY plan VARCHAR(50) NOT NULL DEFAULT 'basico'");
        }

        // 2. Ejecutar la migración de datos de forma universal
        DB::statement("UPDATE licenses SET plan = 'basico' WHERE plan = 'basic'");
        DB::statement("UPDATE licenses SET plan = 'premium' WHERE plan IN ('pro', 'enterprise')");

        // 3. Volver a aplicar el ENUM con los tipos correctos
        if ($driver === 'pgsql') {
            // Para Postgres, lo más seguro es agregar nuevamente un CHECK constraint estilo Laravel
            DB::statement("ALTER TABLE licenses ADD CONSTRAINT licenses_plan_check CHECK (plan::text = ANY (ARRAY['basico'::character varying, 'premium'::character varying]::text[]))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE licenses MODIFY plan ENUM('basico', 'premium') NOT NULL DEFAULT 'basico'");
        } else {
            // SQLite: Laravel gestiona la recreación de la tabla internamente
            Schema::table('licenses', function (Blueprint $table) {
                // Change triggers the internal reconstruction for SQLite
                $table->enum('plan', ['basico', 'premium'])->default('basico')->change();
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE licenses DROP CONSTRAINT IF EXISTS licenses_plan_check");
            DB::statement("ALTER TABLE licenses DROP CONSTRAINT IF EXISTS licenses_plan_type_check");
            DB::statement("ALTER TABLE licenses ALTER COLUMN plan TYPE VARCHAR(50)");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE licenses MODIFY plan VARCHAR(50) NOT NULL DEFAULT 'basic'");
        }

        DB::statement("UPDATE licenses SET plan = 'basic' WHERE plan = 'basico'");
        DB::statement("UPDATE licenses SET plan = 'pro' WHERE plan = 'premium'");

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE licenses ADD CONSTRAINT licenses_plan_check CHECK (plan::text = ANY (ARRAY['basic'::character varying, 'pro'::character varying, 'enterprise'::character varying]::text[]))");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE licenses MODIFY plan ENUM('basic', 'pro', 'enterprise') NOT NULL DEFAULT 'basic'");
        } else {
            Schema::table('licenses', function (Blueprint $table) {
                $table->enum('plan', ['basic', 'pro', 'enterprise'])->default('basic')->change();
            });
        }
    }
};
