<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->string('ci', 11)->nullable()->change();
            $table->string('origen', 20)->default('manual')->after('estado');
            $table->string('samaccountname')->nullable()->unique()->after('origen');
            $table->string('cargo')->nullable()->after('samaccountname');
            $table->string('email')->nullable()->after('cargo');
        });
    }

    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->string('ci')->nullable(false)->change();
            $table->dropColumn(['origen', 'samaccountname', 'cargo', 'email']);
        });
    }
};
