<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controles_soporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->date('record_date')->nullable();
            $table->string('area')->nullable();
            $table->string('support_number')->nullable();
            $table->text('content_summary')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controles_soporte');
    }
};
