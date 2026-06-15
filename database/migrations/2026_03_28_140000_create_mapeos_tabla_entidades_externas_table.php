<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapeos_tabla_entidades_externas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_entities_pg_setting_id')->constrained('ajustes_entidades_externas')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->string('target', 20)->default('entity');
            $table->string('schema_name')->nullable();
            $table->string('table_name');
            $table->string('name_column');
            $table->string('code_column');
            $table->string('municipio_code_column')->nullable();
            $table->string('provincia_code_column')->nullable();
            $table->string('sigla_2_column')->nullable();
            $table->string('sigla_3_column')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapeos_tabla_entidades_externas');
    }
};
