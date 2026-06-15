<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->string('type', 64);
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['equipment_file_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_alertas');
    }
};
