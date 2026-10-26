<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->string('sha256', 64)->nullable()->after('download_url');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn('sha256');
        });
    }
};
