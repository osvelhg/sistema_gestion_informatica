<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_ldap', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(389);
            $table->string('base_dn')->nullable();
            $table->string('bind_username')->nullable();
            $table->text('bind_password')->nullable();
            $table->boolean('use_ssl')->default(false);
            $table->boolean('use_tls')->default(false);
            $table->unsignedSmallInteger('timeout')->default(5);
            $table->string('user_search_base')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_ldap');
    }
};
