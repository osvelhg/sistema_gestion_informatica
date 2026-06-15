<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelos_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_type_id')->constrained('tipos_componentes')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos_componentes');
    }
};
