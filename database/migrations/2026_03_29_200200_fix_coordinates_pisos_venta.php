<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza coordenadas numéricas de pisos_venta que fueron almacenadas como
 * enteros (p.ej. -82991989 en lugar de -82.991989) por el bug de overflow
 * en la importación del Excel.
 *
 * Las columnas latitude/longitude son de tipo NUMERIC, por lo que el ajuste
 * es puramente aritmético: dividir por la primera potencia de 10 que devuelva
 * el valor al rango geográfico válido (±90 para lat, ±180 para lon).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Latitudes fuera de rango (abs > 90)
        DB::statement(<<<'SQL'
            UPDATE pisos_venta
            SET latitude = CASE
                WHEN abs(latitude) / 1000000  <= 90 THEN latitude / 1000000
                WHEN abs(latitude) / 10000000 <= 90 THEN latitude / 10000000
                WHEN abs(latitude) / 100000   <= 90 THEN latitude / 100000
                ELSE NULL
            END
            WHERE latitude IS NOT NULL
              AND abs(latitude) > 90
        SQL);

        // Longitudes fuera de rango (abs > 180)
        DB::statement(<<<'SQL'
            UPDATE pisos_venta
            SET longitude = CASE
                WHEN abs(longitude) / 1000000  <= 180 THEN longitude / 1000000
                WHEN abs(longitude) / 10000000 <= 180 THEN longitude / 10000000
                WHEN abs(longitude) / 100000   <= 180 THEN longitude / 100000
                ELSE NULL
            END
            WHERE longitude IS NOT NULL
              AND abs(longitude) > 180
        SQL);
    }

    public function down(): void
    {
        // No reversible — las coordenadas originales corruptas no pueden restaurarse
    }
};
