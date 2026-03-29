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
        try {
            if (!Schema::hasColumn('licenses', 'installation_id')) {
                Schema::table('licenses', function (Blueprint $table) {
                    $table->string('installation_id')->nullable()->after('expiration_date');
                });
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Postgres error code 42701 = Duplicate column
            if ($e->getCode() !== '42701') {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('licenses', 'installation_id')) {
            Schema::table('licenses', function (Blueprint $table) {
                $table->dropColumn('installation_id');
            });
        }
    }
};
