<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_bd_entidades', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('driver', 10)->default('pgsql');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(5432);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('db_prefix', 20)->default('r4_');
            $table->unsignedTinyInteger('code_padding')->default(0);
            $table->string('table_name')->default('activos');
            $table->string('areas_table')->default('areas_responsabilidad');
            $table->string('area_code_column')->default('codigo');
            $table->string('area_name_column')->default('nombre');
            $table->string('area_column')->default('area_responsabilidad');
            $table->string('grupo_column')->default('grupo');
            $table->string('subgrupo_column')->default('subgrupo');
            $table->unsignedSmallInteger('grupo_value')->default(2);
            $table->unsignedSmallInteger('subgrupo_value')->default(3);
            $table->unsignedSmallInteger('timeout')->default(5);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_bd_entidades');
    }
};
