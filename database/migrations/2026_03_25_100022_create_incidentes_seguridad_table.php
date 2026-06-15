<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidentes_seguridad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->date('incident_date')->nullable();
            $table->string('incident_time', 10)->nullable();
            $table->string('area')->nullable();
            $table->string('consecutive_number')->nullable();
            $table->text('detected_fact')->nullable();
            $table->string('detection_method')->nullable();
            $table->text('measures_taken')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidentes_seguridad');
    }
};
