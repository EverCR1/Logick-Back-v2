<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sucursales ────────────────────────────────────────────
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });

        // ── FK sucursal_id en users (ahora que sucursales existe) ─
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sucursal_id')
                  ->nullable()
                  ->after('last_login_at')
                  ->constrained('sucursales')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
            $table->dropColumn('sucursal_id');
        });

        Schema::dropIfExists('sucursales');
    }
};