<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega la columna business_type para gestionar el tipo de negocio del cliente.
     * El valor por defecto 'retail' garantiza retrocompatibilidad con licencias existentes.
     */
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->enum('business_type', ['retail', 'hardware_store'])
                  ->default('retail')
                  ->after('client_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
