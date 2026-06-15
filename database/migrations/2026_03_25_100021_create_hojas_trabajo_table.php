<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hojas_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->foreignId('inspection_record_id')->nullable()->constrained('registros_inspeccion')->nullOnDelete();
            $table->date('work_date')->nullable();
            $table->string('worksheet_number')->nullable();
            $table->string('control_area')->nullable();
            $table->string('controlled_area')->nullable();
            $table->string('control_action_type')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('preliminary_results')->nullable();
            $table->text('observations')->nullable();
            $table->string('controller_name')->nullable();
            $table->string('controlled_name')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hojas_trabajo');
    }
};
