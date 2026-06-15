<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            $table->unsignedTinyInteger('cidr_prefix')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            $table->dropColumn('cidr_prefix');
        });
    }
};
