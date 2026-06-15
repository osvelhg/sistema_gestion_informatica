<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->enum('category', ['caracteristica', 'periferico', 'dispositivo']);
            $table->string('type')->nullable();
            $table->string('custom_name')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('inventory_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->enum('status', ['Bien', 'Regular', 'Mal'])->default('Bien');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('componentes');
    }
};
