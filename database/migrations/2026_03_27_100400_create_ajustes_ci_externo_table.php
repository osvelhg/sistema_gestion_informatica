<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_ci_externo', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('odbc_dsn')->nullable()->comment('DSN en /etc/odbc.ini. Si se especifica, omite host/port/database_name.');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('database_name')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('table_name')->default('TRABAJADOR');
            $table->string('ci_column')->default('UT_ID');
            $table->string('nombre_column')->default('UT_NOMBRE');
            $table->string('apellido1_column')->nullable();
            $table->string('apellido2_column')->nullable();
            $table->string('telefono_column')->nullable();
            $table->json('direccion_columns')->nullable();
            $table->unsignedSmallInteger('timeout')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_ci_externo');
    }
};
