<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            $table->string('tipo_enlace')->nullable()->after('contracted_speed');
            $table->string('ed')->nullable()->after('tipo_enlace');
            $table->string('ina')->nullable()->after('ed');
            $table->string('id_facturacion')->nullable()->after('ina');
            $table->string('velocidad_etecsa')->nullable()->after('id_facturacion');
            $table->decimal('cuota', 10, 2)->nullable()->after('velocidad_etecsa');
            $table->string('ip_wan', 45)->nullable()->after('cuota');
            $table->string('ip_lan', 45)->nullable()->after('ip_wan');
        });
    }

    public function down(): void
    {
        Schema::table('registros_conectividad', function (Blueprint $table) {
            $table->dropColumn(['tipo_enlace', 'ed', 'ina', 'id_facturacion', 'velocidad_etecsa', 'cuota', 'ip_wan', 'ip_lan']);
        });
    }
};
