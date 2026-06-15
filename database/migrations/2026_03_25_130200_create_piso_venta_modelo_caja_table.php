<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piso_venta_modelo_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_floor_id')->constrained('pisos_venta')->cascadeOnDelete();
            $table->foreignId('cash_register_model_id')->constrained('modelos_caja')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piso_venta_modelo_caja');
    }
};
