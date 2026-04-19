<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed de variantes — cubre los 3 tipos de variante del sistema:
 *
 *  Grupo 1 — Silla Gamer     → variante por COLOR (campo fijo, sin tabla atributos)
 *  Grupo 2 — iPhone 15       → variante por CAPACIDAD (tabla producto_atributos)
 *  Grupo 3 — Camiseta Logick → variante por TALLA (tabla producto_atributos)
 */
class VariantesSeeder extends Seeder
{
    public function run(): void
    {
        // ── Proveedor ─────────────────────────────────────────────────────
        $proveedorId = DB::table('proveedores')->where('nombre', 'Distribuidora Tech GT')->value('id')
            ?? DB::table('proveedores')->insertGetId([
                'nombre'     => 'Distribuidora Tech GT',
                'email'      => 'ventas@techgt.com',
                'telefono'   => '2222-3333',
                'estado'     => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // ── Categorías ────────────────────────────────────────────────────
        $catPer = $this->cat('Periféricos');
        $catTel = $this->cat('Celulares y Accesorios');
        $catRop = $this->cat('Ropa');

        // ═══════════════════════════════════════════════════════════════════
        //  GRUPO 1 — Silla Gamer (variantes por color — sin atributos tabla)
        // ═══════════════════════════════════════════════════════════════════
        $grupoSilla = 'silla-gamer-omega-pro';

        $sillas = [
            [
                'sku'   => 'SILLA-001-NEG',
                'color' => 'Negro',
                'precio_compra' => 1200.00,
                'precio_venta'  => 1999.00,
                'precio_oferta' => 1799.00,
                'imagen'        => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&q=80&auto=format&fit=crop',
                'imagen_thumb'  => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=400&q=70&auto=format&fit=crop',
            ],
            [
                'sku'   => 'SILLA-001-ROJ',
                'color' => 'Rojo',
                'precio_compra' => 1250.00,
                'precio_venta'  => 2099.00,
                'precio_oferta' => null,
                'imagen'        => 'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=800&q=80&auto=format&fit=crop',
                'imagen_thumb'  => 'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=400&q=70&auto=format&fit=crop',
            ],
            [
                'sku'   => 'SILLA-001-AZU',
                'color' => 'Azul',
                'precio_compra' => 1250.00,
                'precio_venta'  => 2099.00,
                'precio_oferta' => null,
                'imagen'        => 'https://images.unsplash.com/photo-1593640408182-31c228af44b9?w=800&q=80&auto=format&fit=crop',
                'imagen_thumb'  => 'https://images.unsplash.com/photo-1593640408182-31c228af44b9?w=400&q=70&auto=format&fit=crop',
            ],
        ];

        foreach ($sillas as $s) {
            $id = $this->insertProducto([
                'sku'            => $s['sku'],
                'nombre'         => 'Silla Gamer Omega Pro Ergonómica',
                'marca'          => 'OmegaChair',
                'color'          => $s['color'],
                'proveedor_id'   => $proveedorId,
                'precio_compra'  => $s['precio_compra'],
                'precio_venta'   => $s['precio_venta'],
                'precio_oferta'  => $s['precio_oferta'],
                'stock'          => 10,
                'stock_minimo'   => 2,
                'garantia'       => '2 años',
                'grupo_variante' => $grupoSilla,
                'descripcion'    => 'Silla gaming ergonómica con soporte lumbar ajustable, reposabrazos 4D y respaldo reclinable hasta 180°. Ideal para largas sesiones de trabajo y gaming.',
                'especificaciones' => "Material: Cuero PU\nEspuma: Memory foam 60D\nReposabrazos: 4D ajustables\nReclinación: 90°–180°\nCapacidad máx: 150 kg\nGarantía: 2 años",
            ]);

            $this->insertImagen($id, $s['imagen'], $s['imagen_thumb'], true);
            $this->vincularCategoria($id, $catPer);
            // Sin entradas en producto_atributos — el color es suficiente diferenciador
        }

        // ═══════════════════════════════════════════════════════════════════
        //  GRUPO 2 — iPhone 15 (variantes por CAPACIDAD — tabla atributos)
        // ═══════════════════════════════════════════════════════════════════
        $grupoIphone = 'iphone-15-negro';

        $iphones = [
            [
                'sku'           => 'IPHONE15-128-NEG',
                'precio_compra' => 3800.00,
                'precio_venta'  => 5999.00,
                'precio_oferta' => null,
                'stock'         => 15,
                'capacidad'     => '128 GB',
                'imagen'        => 'https://images.unsplash.com/photo-1696446701796-da61c3c2c4f5?w=800&q=80&auto=format&fit=crop',
                'imagen_thumb'  => 'https://images.unsplash.com/photo-1696446701796-da61c3c2c4f5?w=400&q=70&auto=format&fit=crop',
            ],
            [
                'sku'           => 'IPHONE15-256-NEG',
                'precio_compra' => 4200.00,
                'precio_venta'  => 6799.00,
                'precio_oferta' => 6499.00,
                'stock'         => 12,
                'capacidad'     => '256 GB',
                'imagen'        => 'https://images.unsplash.com/photo-1696446701796-da61c3c2c4f5?w=800&q=80&auto=format&fit=crop',
                'imagen_thumb'  => 'https://images.unsplash.com/photo-1696446701796-da61c3c2c4f5?w=400&q=70&auto=format&fit=crop',
            ],
            [
                'sku'           => 'IPHONE15-512-NEG',
                'precio_compra' => 5100.00,
                'precio_venta'  => 7999.00,
                'precio_oferta' => null,
                'stock'         => 5,
                'capacidad'     => '512 GB',
                'imagen'        => 'https://images.unsplash.com/photo-1696446701796-da61c3c2c4f5?w=800&q=80&auto=format&fit=crop',
                'imagen_thumb'  => 'https://images.unsplash.com/photo-1696446701796-da61c3c2c4f5?w=400&q=70&auto=format&fit=crop',
            ],
        ];

        foreach ($iphones as $p) {
            $id = $this->insertProducto([
                'sku'            => $p['sku'],
                'nombre'         => 'iPhone 15',
                'marca'          => 'Apple',
                'color'          => 'Negro',
                'proveedor_id'   => $proveedorId,
                'precio_compra'  => $p['precio_compra'],
                'precio_venta'   => $p['precio_venta'],
                'precio_oferta'  => $p['precio_oferta'],
                'stock'          => $p['stock'],
                'stock_minimo'   => 2,
                'garantia'       => '1 año Apple',
                'grupo_variante' => $grupoIphone,
                'descripcion'    => 'iPhone 15 con chip A16 Bionic, cámara principal de 48 MP y Dynamic Island. Conector USB-C.',
                'especificaciones' => "Pantalla: 6.1\" Super Retina XDR\nChip: A16 Bionic\nCámara: 48 MP principal\nConector: USB-C\nResistencia: IP68",
            ]);

            $this->insertImagen($id, $p['imagen'], $p['imagen_thumb'], true);
            $this->vincularCategoria($id, $catTel);
            $this->insertAtributo($id, 'Capacidad', $p['capacidad']);
        }

        // ═══════════════════════════════════════════════════════════════════
        //  GRUPO 3 — Memoria USB (variantes por CAPACIDAD, sin color)
        // ═══════════════════════════════════════════════════════════════════
        $grupoUsb = 'memoria-usb-kingston-3-0';

        $usbs = [
            ['sku' => 'USB-KNG-32',  'capacidad' => '32 GB',  'precio_compra' => 35.00, 'precio_venta' => 65.00,  'precio_oferta' => null,  'stock' => 50],
            ['sku' => 'USB-KNG-64',  'capacidad' => '64 GB',  'precio_compra' => 60.00, 'precio_venta' => 109.00, 'precio_oferta' => 95.00, 'stock' => 40],
            ['sku' => 'USB-KNG-128', 'capacidad' => '128 GB', 'precio_compra' => 95.00, 'precio_venta' => 169.00, 'precio_oferta' => null,  'stock' => 30],
        ];

        $imagenUsb      = 'https://images.unsplash.com/photo-1618410320928-25228d811631?w=800&q=80&auto=format&fit=crop';
        $imagenUsbThumb = 'https://images.unsplash.com/photo-1618410320928-25228d811631?w=400&q=70&auto=format&fit=crop';

        foreach ($usbs as $u) {
            $id = $this->insertProducto([
                'sku'            => $u['sku'],
                'nombre'         => 'Memoria USB Kingston DataTraveler 3.0',
                'marca'          => 'Kingston',
                'color'          => null,
                'proveedor_id'   => $proveedorId,
                'precio_compra'  => $u['precio_compra'],
                'precio_venta'   => $u['precio_venta'],
                'precio_oferta'  => $u['precio_oferta'],
                'stock'          => $u['stock'],
                'stock_minimo'   => 5,
                'garantia'       => '5 años',
                'grupo_variante' => $grupoUsb,
                'descripcion'    => 'Memoria USB 3.0 de alta velocidad. Lectura hasta 130 MB/s. Compatible con Windows, Mac y Linux.',
                'especificaciones' => "Interfaz: USB 3.0 (retrocompatible 2.0)\nLectura: hasta 130 MB/s\nEscritura: hasta 40 MB/s\nSistemas: Windows, macOS, Linux\nGarantía: 5 años",
            ]);

            $this->insertImagen($id, $imagenUsb, $imagenUsbThumb, true);
            $this->vincularCategoria($id, $catPer);
            $this->insertAtributo($id, 'Capacidad', $u['capacidad']);
        }

        // ═══════════════════════════════════════════════════════════════════
        //  GRUPO 4 — Camiseta Logick (variantes por TALLA — tabla atributos)
        // ═══════════════════════════════════════════════════════════════════
        $grupoCamiseta = 'camiseta-logick-azul';

        $camisetas = [
            ['sku' => 'CAM-LOG-AZU-S', 'talla' => 'S',  'stock' => 20, 'precio_oferta' => null],
            ['sku' => 'CAM-LOG-AZU-M', 'talla' => 'M',  'stock' => 30, 'precio_oferta' => null],
            ['sku' => 'CAM-LOG-AZU-L', 'talla' => 'L',  'stock' => 25, 'precio_oferta' => 99.00],
            ['sku' => 'CAM-LOG-AZU-XL','talla' => 'XL', 'stock' => 15, 'precio_oferta' => null],
        ];

        $imagenCamiseta      = 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=800&q=80&auto=format&fit=crop';
        $imagenCamisetaThumb = 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&q=70&auto=format&fit=crop';

        foreach ($camisetas as $c) {
            $id = $this->insertProducto([
                'sku'            => $c['sku'],
                'nombre'         => 'Camiseta Logick',
                'marca'          => 'Logick',
                'color'          => 'Azul',
                'proveedor_id'   => $proveedorId,
                'precio_compra'  => 60.00,
                'precio_venta'   => 129.00,
                'precio_oferta'  => $c['precio_oferta'],
                'stock'          => $c['stock'],
                'stock_minimo'   => 5,
                'garantia'       => null,
                'grupo_variante' => $grupoCamiseta,
                'descripcion'    => 'Camiseta 100% algodón peinado, corte unisex. Ideal para uso diario.',
                'especificaciones' => "Material: 100% algodón peinado\nGramaje: 180 g/m²\nCorte: Unisex\nLavado: Máquina fría",
            ]);

            $this->insertImagen($id, $imagenCamiseta, $imagenCamisetaThumb, true);
            $this->vincularCategoria($id, $catRop);
            $this->insertAtributo($id, 'Talla', $c['talla']);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function cat(string $nombre): int
    {
        return DB::table('categorias')->where('nombre', $nombre)->value('id')
            ?? DB::table('categorias')->insertGetId([
                'nombre'     => $nombre,
                'parent_id'  => null,
                'estado'     => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function insertProducto(array $data): int
    {
        // Evita duplicados si se ejecuta varias veces
        $existing = DB::table('productos')->where('sku', $data['sku'])->value('id');
        if ($existing) return $existing;

        return DB::table('productos')->insertGetId(array_merge($data, [
            'estado'     => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function insertImagen(int $productoId, string $url, string $thumb, bool $principal): void
    {
        if (DB::table('imagenes_producto')->where('producto_id', $productoId)->where('es_principal', true)->exists()) {
            return;
        }

        DB::table('imagenes_producto')->insert([
            'producto_id'     => $productoId,
            'url'             => $url,
            'url_thumb'       => $thumb,
            'url_medium'      => str_replace('w=400', 'w=600', $thumb),
            'url_delete'      => null,
            'imgbb_id'        => null,
            'imgbb_data'      => null,
            'disk'            => 'external',
            'nombre_original' => 'seed-image.jpg',
            'mime_type'       => 'image/jpeg',
            'tamaño'          => 120000,
            'es_principal'    => $principal,
            'orden'           => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function vincularCategoria(int $productoId, int $categoriaId): void
    {
        DB::table('categoria_producto')->insertOrIgnore([
            'producto_id'  => $productoId,
            'categoria_id' => $categoriaId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function insertAtributo(int $productoId, string $nombre, string $valor): void
    {
        $existe = DB::table('producto_atributos')
            ->where('producto_id', $productoId)
            ->where('nombre', $nombre)
            ->exists();

        if (!$existe) {
            DB::table('producto_atributos')->insert([
                'producto_id' => $productoId,
                'nombre'      => $nombre,
                'valor'       => $valor,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
