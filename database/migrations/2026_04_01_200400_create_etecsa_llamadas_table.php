<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de hechos: sin timestamps para ahorrar espacio.
        // Inserción siempre masiva mediante insert() en chunks de 500.
        Schema::create('etecsa_llamadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('etecsa_servicios')->cascadeOnDelete();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('lugar', 100)->nullable();
            $table->string('destino', 100)->nullable();
            $table->string('duracion', 20)->nullable(); // "0:05:20"
            $table->decimal('importe', 10, 4)->default(0);

            $table->index('servicio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etecsa_llamadas');
    }
};
