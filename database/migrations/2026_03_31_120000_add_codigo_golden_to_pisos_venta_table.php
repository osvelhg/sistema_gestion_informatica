<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pisos_venta', function (Blueprint $table) {
            $table->string('codigo_golden', 64)->nullable()->after('datacell_piso_id');
        });
    }

    public function down(): void
    {
        Schema::table('pisos_venta', function (Blueprint $table) {
            $table->dropColumn('codigo_golden');
        });
    }
};
