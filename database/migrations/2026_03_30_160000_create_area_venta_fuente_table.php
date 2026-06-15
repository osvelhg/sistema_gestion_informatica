<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_venta_fuente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_venta_id')->constrained('areas_venta')->cascadeOnDelete();
            $table->foreignId('fuente_id')->constrained('fuentes')->cascadeOnDelete();
            /** Clave para unicidad por canal: id real o 0 si la fuente no tiene canal asignado */
            $table->unsignedBigInteger('canal_key')->default(0);
            $table->foreignId('canal_electronico_id')->nullable()->constrained('canales_electronicos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['area_venta_id', 'canal_key']);
            $table->unique(['area_venta_id', 'fuente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_venta_fuente');
    }
};
