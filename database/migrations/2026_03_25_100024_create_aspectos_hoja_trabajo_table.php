<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aspectos_hoja_trabajo', function (Blueprint $table) {
            $table->id();
            $table->enum('section', ['equipamiento', 'software', 'salvas']);
            $table->string('label');
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspectos_hoja_trabajo');
    }
};
