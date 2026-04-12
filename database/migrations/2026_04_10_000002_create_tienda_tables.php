<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Cuentas de clientes del ecommerce ────────────────────────────────
        Schema::create('cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('email')->unique();
            $table->string('password')->nullable();           // null para usuarios de Google
            $table->string('telefono', 20)->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->string('google_id')->nullable()->unique();
            $table->string('avatar')->nullable();

            $table->unsignedInteger('puntos_saldo')->default(0);

            $table->enum('estado', ['activo', 'inactivo', 'suspendido'])->default('activo');
            $table->timestamps();
        });

        // ── Direcciones de entrega ────────────────────────────────────────────
        Schema::create('cuenta_direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->string('alias', 60)->nullable();
            $table->string('nombre_receptor');
            $table->string('telefono', 20);
            $table->string('departamento');
            $table->string('municipio');
            $table->string('direccion');
            $table->string('referencias')->nullable();
            $table->boolean('es_principal')->default(false);
            $table->timestamps();
        });

        // ── Favoritos (pivot cuenta ↔ producto) ───────────────────────────────
        Schema::create('cuenta_favoritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cuenta_id', 'producto_id']);
        });

        // ── Libro contable de puntos ──────────────────────────────────────────
        Schema::create('cuenta_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->enum('tipo', ['compra', 'resena', 'canje', 'ajuste', 'reversion'])->default('ajuste');
            $table->integer('puntos');                        // positivo = ganados, negativo = canjeados
            $table->string('concepto');
            $table->string('referencia_type')->nullable();    // 'resena', 'pedido', 'cupon'
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cuenta_id', 'created_at']);
        });

        // ── Cupones de descuento ──────────────────────────────────────────────
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('descripcion')->nullable();

            $table->enum('tipo', ['porcentaje', 'monto_fijo']);
            $table->decimal('valor', 10, 2);
            $table->decimal('minimo_compra', 10, 2)->nullable();
            $table->decimal('maximo_descuento', 10, 2)->nullable();

            $table->unsignedInteger('usos_maximos')->nullable();
            $table->unsignedInteger('usos_actuales')->default(0);
            $table->unsignedInteger('usos_por_cuenta')->default(1);
            $table->boolean('solo_primera_compra')->default(false);
            $table->boolean('es_publico')->default(true);

            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_vencimiento')->nullable();

            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();

            $table->index('codigo');
            $table->index('estado');
        });

        // ── Cupones asignados a cuentas específicas ───────────────────────────
        Schema::create('cuenta_cupones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('cupon_id')->constrained('cupones')->cascadeOnDelete();
            $table->unsignedInteger('usos')->default(0);
            $table->timestamps();

            $table->unique(['cuenta_id', 'cupon_id']);
        });

        // ── Pedidos de tienda ─────────────────────────────────────────────────
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_pedido', 20)->unique();

            // Datos del cliente
            $table->string('nombre');
            $table->string('telefono', 20);
            $table->string('email');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();

            // Dirección de entrega
            $table->string('departamento');
            $table->string('municipio');
            $table->string('direccion');
            $table->string('referencias')->nullable();

            // Pago y estado
            $table->enum('metodo_pago', ['efectivo', 'deposito_transferencia', 'tarjeta', 'mixto'])->default('efectivo');
            $table->string('comprobante_url')->nullable();
            $table->string('comprobante_imgbb_id')->nullable();
            $table->enum('estado', [
                'pendiente',
                'confirmado',
                'en_preparacion',
                'enviado',
                'entregado',
                'cancelado',
            ])->default('pendiente');

            // Cupón aplicado
            $table->foreignId('cupon_id')->nullable()->constrained('cupones')->nullOnDelete();
            $table->decimal('descuento_cupon', 10, 2)->default(0);

            // Totales
            $table->decimal('subtotal',    10, 2)->default(0);
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->decimal('total',       10, 2)->default(0);

            // Puntos de fidelidad ganados con este pedido
            $table->unsignedInteger('puntos_ganados')->default(0);

            // Notas
            $table->text('notas')->nullable();
            $table->text('notas_internas')->nullable();

            $table->timestamps();
        });

        // ── Detalle de pedidos ────────────────────────────────────────────────
        Schema::create('pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();

            // Snapshot al momento de la compra
            $table->string('nombre_producto');
            $table->decimal('precio_unitario', 10, 2);
            $table->integer('cantidad');
            $table->decimal('subtotal',        10, 2);

            $table->timestamps();
        });

        // ── Reseñas de productos ──────────────────────────────────────────────
        Schema::create('resenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();

            $table->tinyInteger('rating');
            $table->text('comentario')->nullable();
            $table->unsignedInteger('puntos_otorgados')->default(0);

            $table->enum('estado', ['pendiente', 'publicado', 'rechazado'])->default('pendiente');
            $table->timestamps();

            $table->unique(['cuenta_id', 'producto_id']);
            $table->index(['producto_id', 'estado']);
        });

        // ── Preguntas sobre productos ─────────────────────────────────────────
        Schema::create('producto_preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();

            $table->text('pregunta');
            $table->text('respuesta')->nullable();
            $table->foreignId('respondido_por')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('estado', ['pendiente', 'respondida', 'rechazada'])->default('pendiente');
            $table->timestamps();

            $table->index(['producto_id', 'estado']);
        });

        // ── Solicitudes de garantía ───────────────────────────────────────────
        Schema::create('garantias', function (Blueprint $table) {
            $table->id();
            $table->string('numero_garantia', 20)->unique();

            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('pedido_detalle_id')->nullable()->constrained('pedido_detalles')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('nombre_producto');

            $table->enum('tipo', [
                'defecto_fabrica',
                'dano_fisico',
                'no_funciona',
                'otro',
            ]);
            $table->text('descripcion_problema');
            $table->json('imagenes')->nullable();

            $table->enum('estado', [
                'recibida',
                'en_revision',
                'aprobada',
                'rechazada',
                'en_proceso',
                'resuelta',
            ])->default('recibida');

            $table->text('resolucion')->nullable();
            $table->timestamps();

            $table->index(['cuenta_id', 'estado']);
        });

        // ── Hilo de mensajes por garantía ─────────────────────────────────────
        Schema::create('garantia_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garantia_id')->constrained('garantias')->cascadeOnDelete();

            $table->enum('autor_tipo', ['cliente', 'admin']);
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('mensaje');
            $table->json('archivos')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garantia_mensajes');
        Schema::dropIfExists('garantias');
        Schema::dropIfExists('producto_preguntas');
        Schema::dropIfExists('resenas');
        Schema::dropIfExists('pedido_detalles');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('cuenta_cupones');
        Schema::dropIfExists('cupones');
        Schema::dropIfExists('cuenta_puntos');
        Schema::dropIfExists('cuenta_favoritos');
        Schema::dropIfExists('cuenta_direcciones');
        Schema::dropIfExists('cuentas');
    }
};