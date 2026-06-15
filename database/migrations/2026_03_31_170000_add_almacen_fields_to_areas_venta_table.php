<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas_venta', function (Blueprint $table) {
            $table->integer('almacen_id')->nullable()->after('terminal_id');
            $table->integer('id_almacen_local')->nullable()->after('almacen_id');
            $table->string('almacen_tipo', 50)->nullable()->after('id_almacen_local');
            $table->boolean('almacen_abierto')->default(false)->after('almacen_tipo');
            $table->boolean('almacen_mlc')->default(false)->after('almacen_abierto');
        });
    }

    public function down(): void
    {
        Schema::table('areas_venta', function (Blueprint $table) {
            $table->dropColumn([
                'almacen_id',
                'id_almacen_local',
                'almacen_tipo',
                'almacen_abierto',
                'almacen_mlc',
            ]);
        });
    }
};
