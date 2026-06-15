<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etecsa_servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('etecsa_facturas')->cascadeOnDelete();
            $table->foreignId('connectivity_record_id')
                ->nullable()
                ->constrained('registros_conectividad')
                ->nullOnDelete();
            // Fallback para servicios de telefonía sin registro en ConnectivityRecord
            $table->string('numero_servicio', 20)->nullable();
            $table->decimal('cuota_facturada', 12, 2)->default(0);
            $table->decimal('consumo', 12, 2)->default(0);
            $table->decimal('comision', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total_servicio', 12, 2)->default(0);
            $table->timestamps();

            $table->index('connectivity_record_id');
            // Unicidad parcial: un servicio (por connectivity_record) no puede aparecer 2 veces en la misma factura
            $table->unique(['factura_id', 'connectivity_record_id'], 'uq_factura_connectivity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etecsa_servicios');
    }
};
