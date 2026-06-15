<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('ad_provincia_sigla', 16)->nullable()->after('active')
                ->comment('Primera componente DC= del DN de AD, p.ej. ART');
            $table->string('entity_access_mode', 32)->default('restricted_entities')->after('ad_provincia_sigla')
                ->comment('province_directory | restricted_entities');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['ad_provincia_sigla', 'entity_access_mode']);
        });
    }
};
