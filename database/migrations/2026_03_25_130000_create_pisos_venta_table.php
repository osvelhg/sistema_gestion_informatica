<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pisos_venta', function (Blueprint $table) {
            $table->id();
            $table->string('municipio_code', 20)->nullable();
            $table->foreignId('entity_id')->nullable()->constrained('entidades')->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('network_type_id')->nullable()->constrained('tipos_red')->nullOnDelete();
            $table->foreignId('establishment_type_id')->nullable()->constrained('tipos_establecimiento')->nullOnDelete();
            $table->foreignId('establishment_status_id')->nullable()->constrained('estados_establecimiento')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('datacell_unit_id')->nullable();
            $table->string('datacell_piso_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pisos_venta');
    }
};
