<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * En PostgreSQL no podemos capturar errores con try/catch porque la BD 
     * aborta inmediatamente la transacción entera (SQLSTATE[25P02]). 
     * Por ende, usamos sentencias puras con IF EXISTS.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Eliminar de forma segura sin disparar Errores SQL (que abortarían la transacción)
            DB::statement('ALTER TABLE releases DROP CONSTRAINT IF EXISTS releases_version_unique');
            
            // Comprobar existencia antes de crear para evitar Duplicate Constraint Error
            $constraintExists = DB::selectOne(
                "SELECT 1 FROM pg_constraint WHERE conname = 'releases_version_component_unique'"
            ) !== null;

            if (!$constraintExists) {
                DB::statement('ALTER TABLE releases ADD CONSTRAINT releases_version_component_unique UNIQUE (version, component)');
            }
        } else {
            // Entornos locales (SQLite / MySQL) - try/catch es suficiente ya que suelen 
            // tolerarlo o simplemente se corren de a una sentencia.
            Schema::table('releases', function (Blueprint $table) {
                $indexes = Schema::getIndexes('releases');
                $hasVersionIndex = collect($indexes)->contains('name', 'releases_version_unique');
                
                if ($hasVersionIndex) {
                    $table->dropUnique('releases_version_unique');
                }
                
                $hasCompIndex = collect($indexes)->contains('name', 'releases_version_component_unique');
                if (!$hasCompIndex) {
                    $table->unique(['version', 'component']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE releases DROP CONSTRAINT IF EXISTS releases_version_component_unique');
            
            $constraintExists = DB::selectOne(
                "SELECT 1 FROM pg_constraint WHERE conname = 'releases_version_unique'"
            ) !== null;

            if (!$constraintExists) {
                DB::statement('ALTER TABLE releases ADD CONSTRAINT releases_version_unique UNIQUE (version)');
            }
        } else {
            Schema::table('releases', function (Blueprint $table) {
                $indexes = Schema::getIndexes('releases');
                
                if (collect($indexes)->contains('name', 'releases_version_component_unique')) {
                    $table->dropUnique('releases_version_component_unique');
                }
                
                if (!collect($indexes)->contains('name', 'releases_version_unique')) {
                    $table->unique('version');
                }
            });
        }
    }
};
