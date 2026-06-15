<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('telefono', 32)->nullable()->after('name');
        });

        Schema::table('etecsa_servicios', function (Blueprint $table) {
            $table->foreignId('sales_floor_id')
                ->nullable()
                ->after('connectivity_record_id')
                ->constrained('pisos_venta')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->after('sales_floor_id')
                ->constrained('departamentos')
                ->nullOnDelete();
            $table->string('match_source', 32)->nullable()->after('department_id');
            $table->index(['sales_floor_id']);
            $table->index(['department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('etecsa_servicios', function (Blueprint $table) {
            $table->dropForeign(['sales_floor_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['sales_floor_id', 'department_id', 'match_source']);
        });

        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn('telefono');
        });
    }
};
