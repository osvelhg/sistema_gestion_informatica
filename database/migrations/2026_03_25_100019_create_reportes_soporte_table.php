<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_soporte', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('entity_id')->constrained('entidades')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('equipment_file_id')->nullable()->constrained('expedientes_equipos')->nullOnDelete();
            $table->foreignId('incident_type_id')->nullable()->constrained('tipos_incidentes')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reported_by')->nullable();
            $table->string('status', 30)->default('Abierto');
            $table->string('priority', 20)->default('Media');
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_soporte');
    }
};
