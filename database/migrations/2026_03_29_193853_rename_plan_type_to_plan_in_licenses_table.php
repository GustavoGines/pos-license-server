<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            // Renombrar actual plan_type (basic, pro, etc) a 'plan'
            $table->renameColumn('plan_type', 'plan');
        });

        Schema::table('licenses', function (Blueprint $table) {
            // Agregar nuevo plan_type (saas, lifetime)
            $table->enum('plan_type', ['saas', 'lifetime'])->default('saas')->after('plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('plan_type');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->renameColumn('plan', 'plan_type');
        });
    }
};
