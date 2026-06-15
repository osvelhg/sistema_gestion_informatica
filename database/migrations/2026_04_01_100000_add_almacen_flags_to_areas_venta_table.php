<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas_venta', function (Blueprint $table) {
            // Código contable Golden
            $table->string('almacen_e_contable', 60)->nullable()->after('almacen_mlc');
            // Flags operativos importados desde Golden/Almacenes
            $table->boolean('almacen_exhibicion')->default(false)->after('almacen_e_contable');
            $table->boolean('almacen_interno')->default(false)->after('almacen_exhibicion');
            $table->boolean('almacen_merma')->default(false)->after('almacen_interno');
            $table->boolean('almacen_gastronomia')->default(false)->after('almacen_merma');
            $table->boolean('almacen_insumo')->default(false)->after('almacen_gastronomia');
            $table->boolean('almacen_inversiones')->default(false)->after('almacen_insumo');
            $table->boolean('almacen_boutique')->default(false)->after('almacen_inversiones');
            $table->boolean('almacen_merma_origen')->default(false)->after('almacen_boutique');
            $table->boolean('almacen_consignacion')->default(false)->after('almacen_merma_origen');
            $table->boolean('almacen_emergente')->default(false)->after('almacen_consignacion');
            $table->boolean('almacen_despacho_div')->default(false)->after('almacen_emergente');
            $table->boolean('almacen_distribuir')->default(false)->after('almacen_despacho_div');
            $table->boolean('almacen_mercancia_venta')->default(false)->after('almacen_distribuir');
        });
    }

    public function down(): void
    {
        Schema::table('areas_venta', function (Blueprint $table) {
            $table->dropColumn([
                'almacen_e_contable',
                'almacen_exhibicion',
                'almacen_interno',
                'almacen_merma',
                'almacen_gastronomia',
                'almacen_insumo',
                'almacen_inversiones',
                'almacen_boutique',
                'almacen_merma_origen',
                'almacen_consignacion',
                'almacen_emergente',
                'almacen_despacho_div',
                'almacen_distribuir',
                'almacen_mercancia_venta',
            ]);
        });
    }
};
