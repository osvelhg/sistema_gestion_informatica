<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('system_name')->nullable();
            $table->string('header_title')->nullable();
            $table->string('footer_left')->nullable();
            $table->string('footer_right')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_sistema');
    }
};
