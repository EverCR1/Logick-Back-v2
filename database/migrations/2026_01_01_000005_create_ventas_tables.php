<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Ventas ────────────────────────────────────────────────
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_venta')->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->enum('estado', ['pendiente', 'completada', 'cancelada'])->default('completada');
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia', 'mixto', 'credito'])->default('efectivo');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('numero_venta');
            $table->index('estado');
            $table->index('metodo_pago');
        });

        // ── Venta detalles ────────────────────────────────────────
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->enum('tipo', ['producto', 'servicio', 'otro'])->default('producto');
            $table->integer('cantidad')->default(1);
            $table->string('descripcion');
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->string('referencia')->nullable();
            $table->timestamps();

            $table->index('venta_id');
            $table->index('tipo');
            $table->index('producto_id');
            $table->index('servicio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};