<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_almacenes_externos', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(1433);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('database_name')->default('UnidadesComerciales');
            $table->string('table_name', 100)->default('Almacenes');
            $table->string('schema_name', 50)->default('dbo');
            // Mapeo de columnas configurables
            $table->string('id_unidad_column', 100)->default('IdUnidad');
            $table->string('almacen_column', 100)->default('Almacen');
            $table->string('id_piso_column', 100)->default('IdPiso');
            $table->string('id_almacen_pk_column', 100)->default('IdGerenciaIdAlmacen');
            // Filtros de importación
            $table->boolean('import_solo_abierto')->default(true);
            $table->json('import_tipos')->nullable();
            // Comportamiento de sync
            $table->boolean('sync_creates_areas')->default(true);
            $table->unsignedTinyInteger('timeout')->default(10);
            // Estado de sincronización
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_almacenes_externos');
    }
};
