<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_fincimex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_floor_id')->nullable()->constrained('pisos_venta')->nullOnDelete();
            // Cantidades terminales POS / TPV
            $table->unsignedSmallInteger('tpv_boxes')->default(0);
            $table->unsignedSmallInteger('pos_phone_qty')->default(0);
            $table->unsignedSmallInteger('pos_ip_qty')->default(0);
            $table->unsignedSmallInteger('pos_ip_demand')->default(0);
            $table->unsignedSmallInteger('pos_gprs_qty')->default(0);
            $table->unsignedSmallInteger('pos_gprs_demand')->default(0);
            $table->boolean('has_ip_connectivity')->default(false);
            $table->unsignedSmallInteger('broken_pos_qty')->default(0);
            // Modelo de caja y moneda del POS
            $table->unsignedTinyInteger('cash_register_model_code')->nullable(); // 1=Casio, 2=Óptima, 3=Apos04, 4=Apos05, 5=PC
            $table->boolean('pos_currency_mlc')->default(false);
            $table->boolean('pos_currency_cup')->default(false);
            // QR FINCIMEX instalado + source
            $table->boolean('qr_fincimex_mlc')->default(false);
            $table->boolean('qr_fincimex_cup')->default(false);
            $table->string('src_fincimex_mlc')->nullable();
            $table->string('src_fincimex_cup')->nullable();
            // Identificación del terminal
            $table->string('terminal_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_fincimex');
    }
};
