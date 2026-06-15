<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etecsa_trafico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('etecsa_servicios')->cascadeOnDelete();
            // local_metrado | larga_distancia | cargos_miscelaneos
            $table->string('categoria', 50);
            // "Tráfico Local No Bonificado", "Acceso a Redes Móviles", "Servicio Hora Exacta", etc.
            $table->string('subcategoria', 100)->nullable();
            $table->decimal('importe', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['servicio_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etecsa_trafico');
    }
};
