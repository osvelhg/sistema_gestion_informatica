<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            $table->string('wan_cidr', 64)->nullable()->after('ip_wan');
            $table->string('lan_cidr', 64)->nullable()->after('ip_lan');
        });
    }

    public function down(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            $table->dropColumn(['wan_cidr', 'lan_cidr']);
        });
    }
};
