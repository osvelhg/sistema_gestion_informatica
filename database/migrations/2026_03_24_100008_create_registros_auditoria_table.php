<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->nullableMorphs('auditable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_auditoria');
    }
};
