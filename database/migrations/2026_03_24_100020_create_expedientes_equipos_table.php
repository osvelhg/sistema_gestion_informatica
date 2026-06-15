<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes_equipos', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->unique();
            $table->foreignId('entity_id')->constrained('entidades')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->enum('type', ['PC', 'Laptop'])->default('PC');
            $table->string('inventory_number')->unique()->nullable();
            $table->string('chassis')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('station_name')->nullable();
            $table->string('operating_system')->nullable();
            $table->enum('status', ['Bien', 'Regular', 'Mal'])->default('Bien');
            $table->enum('repairable', ['Si', 'No'])->default('Si');
            $table->string('responsible')->nullable();
            $table->string('seal_code')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes_equipos');
    }
};
