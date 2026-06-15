<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuente_trabajador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('fuentes')->cascadeOnDelete();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->foreignId('rolqr_id')->nullable()->constrained('roles_qr')->nullOnDelete();
            $table->date('fecha_alta')->nullable();
            $table->date('fecha_baja')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuente_trabajador');
    }
};
