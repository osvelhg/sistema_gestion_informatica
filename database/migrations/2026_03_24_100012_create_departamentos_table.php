<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entidades')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->string('codigo_area', 50)->nullable();
            $table->string('codigo_entidad', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['codigo_entidad', 'codigo_area'], 'dept_codigo_entidad_area_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
