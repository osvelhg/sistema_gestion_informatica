<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piso_venta_fuente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_floor_id')->constrained('pisos_venta')->cascadeOnDelete();
            $table->foreignId('fuente_id')->constrained('fuentes')->cascadeOnDelete();
            $table->unsignedBigInteger('canal_key')->default(0);
            $table->foreignId('canal_electronico_id')->nullable()->constrained('canales_electronicos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_floor_id', 'canal_key']);
            $table->unique(['sales_floor_id', 'fuente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piso_venta_fuente');
    }
};
