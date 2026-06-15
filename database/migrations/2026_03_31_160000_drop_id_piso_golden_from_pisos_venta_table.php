<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pisos_venta', function (Blueprint $table) {
            if (Schema::hasColumn('pisos_venta', 'id_piso_golden')) {
                $table->dropColumn('id_piso_golden');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pisos_venta', function (Blueprint $table) {
            if (!Schema::hasColumn('pisos_venta', 'id_piso_golden')) {
                $table->unsignedBigInteger('id_piso_golden')->nullable()->after('codigo_golden');
            }
        });
    }
};
