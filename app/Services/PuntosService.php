<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\CuentaPunto;
use App\Models\Pedido;
use App\Models\Resena;
use App\Models\Cupon;
use Illuminate\Support\Str;

class PuntosService
{
    // ── Puntos por compra: Q10 = 1 punto ───────────────────────────────────────

    public function otorgarPorCompra(Pedido $pedido): void
    {
        // Evitar duplicados
        if (CuentaPunto::where('referencia_type', 'pedido')
                        ->where('referencia_id', $pedido->id)
                        ->where('tipo', 'compra')
                        ->exists()) {
            return;
        }

        $puntos = (int) floor($pedido->total / 10);
        if ($puntos <= 0) return;

        $this->registrar(
            cuentaId:      $pedido->cuenta_id,
            tipo:          'compra',
            puntos:        $puntos,
            referenciaId:  $pedido->id,
            referenciaTipo:'pedido',
            descripcion:   "Compra #{$pedido->numero_pedido} — {$puntos} puntos",
        );
    }

    public function revertirPorCompra(Pedido $pedido): void
    {
        $movimiento = CuentaPunto::where('referencia_type', 'pedido')
                                  ->where('referencia_id', $pedido->id)
                                  ->where('tipo', 'compra')
                                  ->first();

        if (!$movimiento) return;

        $this->registrar(
            cuentaId:      $pedido->cuenta_id,
            tipo:          'reversion',
            puntos:        -$movimiento->puntos,
            referenciaId:  $pedido->id,
            referenciaTipo:'pedido',
            descripcion:   "Reversión compra #{$pedido->numero_pedido}",
        );
    }

    // ── Puntos por reseña: escala por precio del producto ─────────────────────

    public function otorgarPorResena(Resena $resena): void
    {
        if (CuentaPunto::where('referencia_type', 'resena')
                        ->where('referencia_id', $resena->id)
                        ->where('tipo', 'resena')
                        ->exists()) {
            return;
        }

        $precio = (float) optional($resena->producto)->precio_final ?? 0;
        $puntos = $this->calcularPuntosResena($precio);

        $this->registrar(
            cuentaId:      $resena->cuenta_id,
            tipo:          'resena',
            puntos:        $puntos,
            referenciaId:  $resena->id,
            referenciaTipo:'resena',
            descripcion:   "Reseña aprobada — {$puntos} puntos",
        );
    }

    public function revertirPorResena(Resena $resena): void
    {
        $movimiento = CuentaPunto::where('referencia_type', 'resena')
                                  ->where('referencia_id', $resena->id)
                                  ->where('tipo', 'resena')
                                  ->first();

        if (!$movimiento) return;

        $this->registrar(
            cuentaId:      $resena->cuenta_id,
            tipo:          'reversion',
            puntos:        -$movimiento->puntos,
            referenciaId:  $resena->id,
            referenciaTipo:'resena',
            descripcion:   "Reversión reseña rechazada",
        );
    }

    // ── Canje de puntos por cupón ──────────────────────────────────────────────

    /**
     * Opciones de canje disponibles (puntos => quetzales).
     */
    public static function opcionesCanje(): array
    {
        return [
            50   => 5,
            100  => 10,
            150  => 15,
            200  => 20,
            250  => 25,
            300  => 30,
            500  => 50,
            750  => 75,
            1000 => 100,
        ];
    }

    public function canjear(Cuenta $cuenta, int $puntos): Cupon
    {
        $opciones = self::opcionesCanje();

        if (!isset($opciones[$puntos])) {
            throw new \InvalidArgumentException('Opción de canje no válida.');
        }

        if ($cuenta->puntos_saldo < $puntos) {
            throw new \RuntimeException('Saldo de puntos insuficiente.');
        }

        $quetzales = $opciones[$puntos];

        // Crear cupón único para esta cuenta
        $cupon = Cupon::create([
            'codigo'           => 'PUNTOS-' . strtoupper(substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 8)),
            'descripcion'      => "Canjeado con {$puntos} puntos",
            'tipo'             => 'monto_fijo',
            'valor'            => $quetzales,
            'usos_maximos'     => null,
            'usos_por_cuenta'  => 1,
            'fecha_inicio'        => now(),
            'fecha_vencimiento'   => now()->addMonths(6),
            'estado'           => 'activo',
            'es_privado'       => true,
        ]);

        // Asignar a la cuenta
        $cupon->cuentas()->attach($cuenta->id);

        // Registrar movimiento negativo
        $this->registrar(
            cuentaId:      $cuenta->id,
            tipo:          'canje',
            puntos:        -$puntos,
            referenciaId:  $cupon->id,
            referenciaTipo:'cupon',
            descripcion:   "Canje por cupón {$cupon->codigo} (Q{$quetzales})",
        );

        return $cupon;
    }

    // ── Helper interno ────────────────────────────────────────────────────────

    private function calcularPuntosResena(float $precio): int
    {
        return match(true) {
            $precio > 3000  => 200,
            $precio > 1500  => 150,
            $precio > 500   => 100,
            $precio > 100   => 50,
            default         => 20,
        };
    }

    private function registrar(
        int $cuentaId,
        string $tipo,
        int $puntos,
        ?int $referenciaId,
        ?string $referenciaTipo,
        string $descripcion,
    ): void {
        CuentaPunto::create([
            'cuenta_id'      => $cuentaId,
            'tipo'           => $tipo,
            'puntos'         => $puntos,
            'referencia_id'  => $referenciaId,
            'referencia_type'=> $referenciaTipo,
            'concepto'       => $descripcion,
        ]);

        // Actualizar saldo en la cuenta
        Cuenta::where('id', $cuentaId)->increment('puntos_saldo', $puntos);
    }
}