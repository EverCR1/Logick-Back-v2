<?php

namespace Database\Seeders;

use App\Models\Cuenta;
use App\Models\Cupon;
use Illuminate\Database\Seeder;

class CuponSeeder extends Seeder
{
    public function run(): void
    {
        $cupones = [
            // Porcentaje simple — sin restricciones
            [
                'codigo'      => 'PRUEBA10',
                'descripcion' => '10% de descuento en tu pedido',
                'tipo'        => 'porcentaje',
                'valor'       => 10.00,
            ],

            // Monto fijo con mínimo de compra
            [
                'codigo'        => 'DESCUENTO50',
                'descripcion'   => 'Q50 de descuento en compras mayores a Q300',
                'tipo'          => 'monto_fijo',
                'valor'         => 50.00,
                'minimo_compra' => 300.00,
            ],

            // Porcentaje con tope máximo
            [
                'codigo'           => 'BLACK20',
                'descripcion'      => '20% de descuento, máximo Q100',
                'tipo'             => 'porcentaje',
                'valor'            => 20.00,
                'maximo_descuento' => 100.00,
            ],

            // Solo primera compra (requiere cuenta autenticada)
            [
                'codigo'              => 'BIENVENIDO15',
                'descripcion'         => '15% de descuento en tu primera compra',
                'tipo'                => 'porcentaje',
                'valor'               => 15.00,
                'solo_primera_compra' => true,
            ],

            // Uso único global
            [
                'codigo'       => 'UNICO75',
                'descripcion'  => 'Q75 de descuento — uso único',
                'tipo'         => 'monto_fijo',
                'valor'        => 75.00,
                'usos_maximos' => 1,
            ],

            // Con fecha de vencimiento pasada (para probar el rechazo)
            [
                'codigo'            => 'EXPIRADO',
                'descripcion'       => 'Cupón de prueba vencido',
                'tipo'              => 'porcentaje',
                'valor'             => 30.00,
                'fecha_vencimiento' => now()->subDay(),
            ],
        ];

        foreach ($cupones as $data) {
            Cupon::firstOrCreate(['codigo' => $data['codigo']], $data);
        }

        $this->command->info('Cupones de prueba creados: ' . implode(', ', array_column($cupones, 'codigo')));

        // ── Cupón exclusivo para test1@gmail.com ──────────────────────────────
        $cuponVip = Cupon::firstOrCreate(
            ['codigo' => 'VIP25'],
            [
                'descripcion'    => 'Q25 de descuento exclusivo para tu cuenta',
                'tipo'           => 'monto_fijo',
                'valor'          => 25.00,
                'es_publico'     => false,
                'usos_por_cuenta'=> 1,
                'estado'         => 'activo',
            ]
        );

        $cuenta = Cuenta::where('email', 'test1@gmail.com')->first();

        if ($cuenta) {
            // Asignar solo si aún no está vinculado
            if (!$cuenta->cupones()->where('cupon_id', $cuponVip->id)->exists()) {
                $cuenta->cupones()->attach($cuponVip->id, ['usos' => 0]);
                $this->command->info("Cupón VIP25 asignado a test1@gmail.com");
            } else {
                $this->command->warn("Cupón VIP25 ya estaba asignado a test1@gmail.com");
            }
        } else {
            $this->command->warn("No se encontró la cuenta test1@gmail.com — cupón VIP25 creado pero sin asignar");
        }
    }
}