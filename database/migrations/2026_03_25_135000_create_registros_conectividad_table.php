<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_conectividad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->foreignId('sales_floor_id')->nullable()->constrained('pisos_venta')->nullOnDelete();
            $table->string('unit_name')->nullable();
            $table->string('address')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('adsl_mode')->nullable();
            $table->string('provider_identifier')->nullable();
            $table->string('contracted_speed')->nullable();
            $table->string('qr_code')->nullable();
            $table->string('source_sheet')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_conectividad');
    }
};
