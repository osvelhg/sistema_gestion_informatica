<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuentes', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->nullable();
            $table->foreignId('sales_floor_id')->nullable()->constrained('pisos_venta')->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('source_name')->nullable();
            $table->string('moneda')->nullable();
            $table->unsignedBigInteger('id_unidad')->nullable();
            $table->string('unidad_nombre')->nullable();
            $table->foreignId('canal_electronico_id')->nullable()->constrained('canales_electronicos')->nullOnDelete();
            $table->foreignId('tipo_fuente_id')->nullable()->constrained('tipos_fuentes')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->string('id_piso')->nullable();
            $table->string('id_division')->nullable();
            $table->string('division')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuentes');
    }
};
