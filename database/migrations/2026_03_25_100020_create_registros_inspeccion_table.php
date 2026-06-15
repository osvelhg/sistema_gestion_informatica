<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_inspeccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entidades')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->date('inspection_date')->nullable();
            $table->text('participants')->nullable();
            $table->text('situations_detected')->nullable();
            $table->string('worksheet_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_inspeccion');
    }
};
