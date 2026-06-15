<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etecsa_cuotas_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('etecsa_servicios')->cascadeOnDelete();
            $table->string('concepto'); // "Valor Básico", "Extensión del Teléfono", "Conectividad IP", etc.
            $table->decimal('importe', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etecsa_cuotas_detalle');
    }
};
