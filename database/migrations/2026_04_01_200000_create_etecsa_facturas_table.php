<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etecsa_facturas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_factura', 30)->unique();
            $table->string('numero_cliente', 30);
            $table->string('nombre_cliente');
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('codigo_pago_banco', 30)->nullable();
            $table->string('oficina_comercial')->nullable();
            $table->string('zona_postal', 20)->nullable();
            $table->string('moneda', 10)->default('CUP');
            $table->decimal('tasa_cambio', 10, 3)->nullable();
            $table->decimal('total_cuota_mensual', 12, 2)->default(0);
            $table->decimal('total_consumo', 12, 2)->default(0);
            $table->decimal('total_comision', 12, 2)->default(0);
            $table->decimal('total_impuesto', 12, 2)->default(0);
            $table->decimal('total_facturado', 12, 2)->default(0);
            $table->decimal('total_saldo', 12, 2)->default(0);
            $table->decimal('total_a_pagar', 12, 2)->default(0);
            $table->decimal('total_usd', 12, 4)->nullable();
            $table->string('tipo_factura', 20)->default('telefonia'); // telefonia | conectividad | mixta
            $table->string('pdf_hash', 64)->nullable()->index();
            $table->foreignId('imported_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index('periodo_desde');
            $table->index('tipo_factura');
            $table->index('numero_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etecsa_facturas');
    }
};
