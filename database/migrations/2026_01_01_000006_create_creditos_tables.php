<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Créditos ──────────────────────────────────────────────
        Schema::create('creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('nombre_cliente');
            $table->decimal('capital', 15, 2);
            $table->text('producto_o_servicio_dado')->nullable();
            $table->date('fecha_credito');
            $table->date('fecha_ultimo_pago')->nullable();
            $table->decimal('ultima_cantidad_pagada', 15, 2)->nullable();
            $table->decimal('capital_restante', 15, 2);
            $table->enum('estado', ['activo', 'abonado', 'pagado'])->default('activo');
            $table->timestamps();

            $table->index('nombre_cliente');
            $table->index('estado');
            $table->index('fecha_credito');
        });

        // ── Pagos de crédito ──────────────────────────────────────
        Schema::create('pagos_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->onDelete('cascade');
            $table->decimal('monto', 15, 2);
            $table->date('fecha_pago');
            $table->enum('tipo', ['abono', 'pago_total']);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('credito_id');
            $table->index('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_credito');
        Schema::dropIfExists('creditos');
    }
};