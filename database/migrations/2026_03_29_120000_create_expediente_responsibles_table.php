<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_responsibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_file_id')->constrained('expedientes_equipos')->cascadeOnDelete();
            $table->string('display_name');
            $table->string('samaccountname')->nullable();
            $table->string('mail')->nullable();
            $table->string('source', 16)->default('manual');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (Schema::hasTable('expedientes_equipos')) {
            $rows = DB::table('expedientes_equipos')
                ->whereNotNull('responsible')
                ->where('responsible', '!=', '')
                ->orderBy('id')
                ->get(['id', 'responsible']);

            foreach ($rows as $row) {
                DB::table('expediente_responsibles')->insert([
                    'equipment_file_id' => $row->id,
                    'display_name'      => $row->responsible,
                    'samaccountname'    => null,
                    'mail'              => null,
                    'source'            => 'manual',
                    'sort_order'        => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_responsibles');
    }
};
