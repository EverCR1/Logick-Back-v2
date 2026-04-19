<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_problemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->string('categoria', 60);
            $table->text('descripcion');
            $table->string('nombre_contacto', 120)->nullable();
            $table->string('email_contacto', 150)->nullable();
            $table->string('telefono_contacto', 30)->nullable();
            $table->enum('estado', ['pendiente', 'en_revision', 'resuelto', 'invalido'])->default('pendiente');
            $table->unsignedSmallInteger('puntos_otorgados')->nullable();
            $table->text('nota_admin')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_problemas');
    }
};
