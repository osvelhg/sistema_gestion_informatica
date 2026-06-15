<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('registros_fincimex');
    }

    public function down(): void
    {
        // Se recrea en migrate:fresh desde la migración original
    }
};
