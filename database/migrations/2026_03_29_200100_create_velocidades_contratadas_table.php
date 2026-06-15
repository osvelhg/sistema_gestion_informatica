<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('velocidades_contratadas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique()->comment('Valor canónico, ej: 512 Kbps, 1 Mbps, 8 Mbps');
            $table->unsignedInteger('kbps')->nullable()->comment('Valor numérico en Kbps para ordenación y comparación');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Siembra velocidades más comunes
        $velocidades = [
            ['nombre' => '512 Kbps', 'kbps' =>    512],
            ['nombre' => '1 Mbps',   'kbps' =>   1024],
            ['nombre' => '2 Mbps',   'kbps' =>   2048],
            ['nombre' => '4 Mbps',   'kbps' =>   4096],
            ['nombre' => '6 Mbps',   'kbps' =>   6144],
            ['nombre' => '8 Mbps',   'kbps' =>   8192],
            ['nombre' => '10 Mbps',  'kbps' =>  10240],
            ['nombre' => '16 Mbps',  'kbps' =>  16384],
            ['nombre' => '20 Mbps',  'kbps' =>  20480],
            ['nombre' => '32 Mbps',  'kbps' =>  32768],
            ['nombre' => '50 Mbps',  'kbps' =>  51200],
            ['nombre' => '100 Mbps', 'kbps' => 102400],
        ];

        DB::table('velocidades_contratadas')->insert(
            array_map(fn ($v) => array_merge($v, ['activo' => true, 'created_at' => now(), 'updated_at' => now()]), $velocidades)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('velocidades_contratadas');
    }
};
