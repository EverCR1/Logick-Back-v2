<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Productos ─────────────────────────────────────────────
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->text('especificaciones')->nullable();
            $table->string('marca')->nullable();
            $table->string('color')->nullable();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->decimal('precio_compra', 10, 2);
            $table->decimal('precio_venta', 10, 2);
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->integer('stock')->default(1);
            $table->integer('stock_minimo')->default(1);
            $table->string('codigo_barras')->nullable()->unique();
            $table->string('ubicacion')->nullable();
            $table->text('notas_internas')->nullable();
            $table->string('garantia', 50)->nullable();
            $table->timestamps();
        });

        // ── Pivot categoría-producto ──────────────────────────────
        Schema::create('categoria_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['producto_id', 'categoria_id']);
        });

        // ── Imágenes de producto (con campos imgbb) ───────────────
        Schema::create('imagenes_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
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
            $table->boolean('es_principal')->default(false);
            $table->integer('orden')->default(0);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index('imgbb_id');
            $table->index('disk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_producto');
        Schema::dropIfExists('categoria_producto');
        Schema::dropIfExists('productos');
    }
};