<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expediente_responsibles', function (Blueprint $table) {
            $table->foreignId('trabajador_id')->nullable()->after('id')->constrained('trabajadores')->nullOnDelete();
        });

        // Vincular responsables AD existentes a trabajadores (por samaccountname)
        $adResponsibles = DB::table('expediente_responsibles')
            ->where('source', 'ad')
            ->whereNotNull('samaccountname')
            ->whereNull('trabajador_id')
            ->get();

        foreach ($adResponsibles as $resp) {
            $trabajador = DB::table('trabajadores')
                ->where('samaccountname', $resp->samaccountname)
                ->first();

            if (! $trabajador) {
                $trabajadorId = DB::table('trabajadores')->insertGetId([
                    'nombre'         => $resp->display_name,
                    'samaccountname' => $resp->samaccountname,
                    'email'          => $resp->mail,
                    'origen'         => 'active_directory',
                    'estado'         => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            } else {
                $trabajadorId = $trabajador->id;
            }

            DB::table('expediente_responsibles')
                ->where('id', $resp->id)
                ->update(['trabajador_id' => $trabajadorId]);
        }
    }

    public function down(): void
    {
        Schema::table('expediente_responsibles', function (Blueprint $table) {
            $table->dropForeign(['trabajador_id']);
            $table->dropColumn('trabajador_id');
        });
    }
};
