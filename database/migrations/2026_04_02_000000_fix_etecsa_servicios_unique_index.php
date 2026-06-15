<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar la restricción única completa que no permite duplicados
        // cuando dos bloques del PDF resuelven al mismo connectivity_record_id.
        // En PostgreSQL la constraint se llama igual que el índice pero hay que
        // droparla como constraint (no como índice).
        DB::statement('ALTER TABLE etecsa_servicios DROP CONSTRAINT IF EXISTS uq_factura_connectivity');

        // Crear índice parcial: único solo cuando connectivity_record_id NO es null.
        // Esto permite múltiples filas con connectivity_record_id NULL (telefonía sin
        // registro en catálogo) y evita duplicados reales en conectividad.
        DB::statement(
            'CREATE UNIQUE INDEX uq_factura_connectivity
             ON etecsa_servicios (factura_id, connectivity_record_id)
             WHERE connectivity_record_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_factura_connectivity');
        DB::statement(
            'ALTER TABLE etecsa_servicios
             ADD CONSTRAINT uq_factura_connectivity
             UNIQUE (factura_id, connectivity_record_id)'
        );
    }
};
