<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->string('inventory_number')->nullable();
            $table->foreignId('from_entity_id')->nullable()->constrained('entidades')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('to_entity_id')->nullable()->constrained('entidades')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('moved_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('moved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_equipos');
    }
};
