<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modos_adsl', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Código corto: ED, LC, FR, etc.');
            $table->string('nombre', 100)->comment('Nombre descriptivo del modo ADSL');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Siembra los tres modos base
        DB::table('modos_adsl')->insert([
            ['code' => 'ED', 'nombre' => 'Enlace Directo',    'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'LC', 'nombre' => 'Línea Conmutada',   'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'FR', 'nombre' => 'Frame Relay',       'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('modos_adsl');
    }
};
