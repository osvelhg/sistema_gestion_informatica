<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            if (Schema::hasColumn('registros_conectividad', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            if (Schema::hasColumn('registros_conectividad', 'cidr_prefix')) {
                $table->dropColumn('cidr_prefix');
            }
            if (Schema::hasColumn('registros_conectividad', 'adsl_mode')) {
                $table->dropColumn('adsl_mode');
            }
            if (Schema::hasColumn('registros_conectividad', 'provider_identifier')) {
                $table->dropColumn('provider_identifier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            if (! Schema::hasColumn('registros_conectividad', 'ip_address')) {
                $table->string('ip_address', 45)->nullable();
            }
            if (! Schema::hasColumn('registros_conectividad', 'cidr_prefix')) {
                $table->unsignedTinyInteger('cidr_prefix')->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('registros_conectividad', 'adsl_mode')) {
                $table->string('adsl_mode')->nullable();
            }
            if (! Schema::hasColumn('registros_conectividad', 'provider_identifier')) {
                $table->string('provider_identifier')->nullable();
            }
        });
    }
};
