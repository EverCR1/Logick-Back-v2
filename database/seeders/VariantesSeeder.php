<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VariantesSeeder extends Seeder
{
    public function run(): void
    {
        // Reutiliza el proveedor y categoría del TiendaSeeder si ya existen,
        // o los crea si este seeder se ejecuta de forma independiente.
        $proveedorId = DB::table('proveedores')->where('nombre', 'Distribuidora Tech GT')->value('id')
            ?? DB::table('proveedores')->insertGetId([
                'nombre'      => 'Distribuidora Tech GT',
                'email'       => 'ventas@techgt.com',
                'telefono'    => '2222-3333',
                'estado'      => 'activo',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

        $catPerifericosId = DB::table('categorias')->where('nombre', 'Periféricos')->value('id')
            ?? DB::table('categorias')->insertGetId([
                'nombre'     => 'Periféricos',
                'parent_id'  => null,
                'estado'     => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // Grupo de variantes: misma llave para los 3 colores
        $grupo = 'silla-gamer-omega-pro';

        $variantes = [
            [
                'sku'              => 'SILLA-001-NEG',
                'color'            => 'Negro',
                'precio_compra'    => 1200.00,
                'precio_venta'     => 1999.00,
                'precio_oferta'    => 1799.00,
                'imagen'           => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91',
                'imagen_alt'       => 'https://images.unsplash.com/photo-1583153803709-75a321f7c04e',
            ],
            [
                'sku'              => 'SILLA-001-ROJ',
                'color'            => 'Rojo',
                'precio_compra'    => 1250.00,
                'precio_venta'     => 2099.00,
                'precio_oferta'    => null,
                'imagen'           => 'https://images.unsplash.com/photo-1611532736597-de2d4265fba3',
                'imagen_alt'       => null,
            ],
            [
                'sku'              => 'SILLA-001-AZU',
                'color'            => 'Azul',
                'precio_compra'    => 1250.00,
                'precio_venta'     => 2099.00,
                'precio_oferta'    => null,
                'imagen'           => 'https://images.unsplash.com/photo-1593640408182-31c228af44b9',
                'imagen_alt'       => null,
            ],
        ];

        foreach ($variantes as $v) {
            $productoId = DB::table('productos')->insertGetId([
                'sku'              => $v['sku'],
                'nombre'           => 'Silla Gamer Omega Pro Ergonómica',
                'marca'            => 'OmegaChair',
                'color'            => $v['color'],
                'proveedor_id'     => $proveedorId,
                'precio_compra'    => $v['precio_compra'],
                'precio_venta'     => $v['precio_venta'],
                'precio_oferta'    => $v['precio_oferta'],
                'stock'            => 10,
                'stock_minimo'     => 2,
                'garantia'         => '2 años',
                'estado'           => 'activo',
                'grupo_variante'   => $grupo,
                'descripcion'      => 'Silla gaming ergonómica con soporte lumbar ajustable, reposabrazos 4D y respaldo reclinable hasta 180°. Diseñada para largas sesiones de trabajo y gaming.',
                'especificaciones' => "Material: Cuero PU de alta calidad\nEspuma: Memory foam 60D\nReposabrazos: 4D ajustables\nReclinación: 90°–180°\nAltura asiento: 42–52 cm\nCapacidad: 150 kg\nBase: Aluminio con ruedas silencionas\nGarantía: 2 años",
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Imagen principal
            DB::table('imagenes_producto')->insert([
                'producto_id'     => $productoId,
                'url'             => $v['imagen'] . '?w=800&q=80&auto=format&fit=crop',
                'url_thumb'       => $v['imagen'] . '?w=400&q=70&auto=format&fit=crop',
                'url_medium'      => $v['imagen'] . '?w=600&q=75&auto=format&fit=crop',
                'url_delete'      => null,
                'imgbb_id'        => null,
                'imgbb_data'      => null,
                'disk'            => 'external',
                'nombre_original' => $v['sku'] . '-principal.jpg',
                'mime_type'       => 'image/jpeg',
                'tamaño'          => 150000,
                'es_principal'    => true,
                'orden'           => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Segunda imagen (si existe)
            if ($v['imagen_alt']) {
                DB::table('imagenes_producto')->insert([
                    'producto_id'     => $productoId,
                    'url'             => $v['imagen_alt'] . '?w=800&q=80&auto=format&fit=crop',
                    'url_thumb'       => $v['imagen_alt'] . '?w=400&q=70&auto=format&fit=crop',
                    'url_medium'      => $v['imagen_alt'] . '?w=600&q=75&auto=format&fit=crop',
                    'url_delete'      => null,
                    'imgbb_id'        => null,
                    'imgbb_data'      => null,
                    'disk'            => 'external',
                    'nombre_original' => $v['sku'] . '-alt.jpg',
                    'mime_type'       => 'image/jpeg',
                    'tamaño'          => 140000,
                    'es_principal'    => false,
                    'orden'           => 1,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            // Categoría
            DB::table('categoria_producto')->insertOrIgnore([
                'producto_id'  => $productoId,
                'categoria_id' => $catPerifericosId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
}