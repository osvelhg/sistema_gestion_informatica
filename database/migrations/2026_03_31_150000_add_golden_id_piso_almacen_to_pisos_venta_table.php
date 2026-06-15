<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pisos_venta', function (Blueprint $table) {
            $table->unsignedBigInteger('id_piso_golden')->nullable()->after('codigo_golden');
            $table->string('almacen_golden', 255)->nullable()->after('id_piso_golden');
        });

        if (Schema::hasColumn('pisos_venta', 'datacell_piso_id')) {
            DB::table('pisos_venta')
                ->whereNotNull('datacell_piso_id')
                ->whereNull('id_piso_golden')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $raw = (string) ($row->datacell_piso_id ?? '');
                        if ($raw !== '' && ctype_digit($raw)) {
                            DB::table('pisos_venta')->where('id', $row->id)->update(['id_piso_golden' => (int) $raw]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('pisos_venta', function (Blueprint $table) {
            $table->dropColumn(['id_piso_golden', 'almacen_golden']);
        });
    }
};
