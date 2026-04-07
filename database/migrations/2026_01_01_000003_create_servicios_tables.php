<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Servicios ─────────────────────────────────────────────
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('inversion_estimada', 10, 2);
            $table->decimal('precio_venta', 10, 2);
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->text('notas_internas')->nullable();
            $table->timestamps();

            $table->index('codigo');
            $table->index('estado');
        });

        // ── Imágenes de servicio ──────────────────────────────────
        Schema::create('imagenes_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->onDelete('cascade');
            $table->string('url');
            $table->string('url_thumb')->nullable();
            $table->string('url_medium')->nullable();
            $table->string('url_delete')->nullable();
            $table->string('imgbb_id')->nullable();
            $table->text('imgbb_data')->nullable();
            $table->string('disk')->default('public');
            $table->string('nombre_original');
            $table->string('mime_type');
            $table->integer('tamaño');
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index('imgbb_id');
            $table->index('disk');
        });

        // ── Imágenes de categoría ─────────────────────────────────
        Schema::create('imagenes_categoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            $table->string('url');
            $table->string('url_thumb')->nullable();
            $table->string('url_medium')->nullable();
            $table->string('url_delete')->nullable();
            $table->string('imgbb_id')->nullable();
            $table->text('imgbb_data')->nullable();
            $table->string('disk')->default('imgbb');
            $table->string('nombre_original')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('tamaño')->nullable();
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->index('categoria_id');
            $table->index('imgbb_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_categoria');
        Schema::dropIfExists('imagenes_servicio');
        Schema::dropIfExists('servicios');
    }
};