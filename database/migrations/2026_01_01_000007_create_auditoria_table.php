<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->string('usuario_nombre');
            $table->string('usuario_rol');
            $table->string('accion');
            $table->string('modulo');
            $table->string('tabla');
            $table->unsignedBigInteger('registro_id');
            $table->text('descripcion');
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('usuario_id');
            $table->index('modulo');
            $table->index('accion');
            $table->index('created_at');
            $table->index(['modulo', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};