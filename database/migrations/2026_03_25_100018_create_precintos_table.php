<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precintos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entidades')->cascadeOnDelete();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->foreignId('incident_type_id')->nullable()->constrained('tipos_incidentes')->nullOnDelete();
            $table->string('inventory_number')->nullable();
            $table->string('code')->nullable();
            $table->string('removed_seal')->nullable();
            $table->string('applied_seal')->nullable();
            $table->string('reason')->nullable();
            $table->date('date')->nullable();
            $table->string('time', 10)->nullable();
            $table->string('performed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precintos');
    }
};
