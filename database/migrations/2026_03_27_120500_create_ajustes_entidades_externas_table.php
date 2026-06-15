<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_entidades_externas', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 10)->default('pgsql');
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(5432);
            $table->string('database_name')->nullable();
            $table->string('schema_name')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('table_name')->default('entities');
            $table->string('name_column')->default('name');
            $table->string('code_column')->default('code');
            $table->string('municipio_code_column')->nullable();
            $table->string('provincia_column')->nullable();
            $table->unsignedSmallInteger('timeout')->default(5);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_entidades_externas');
    }
};
