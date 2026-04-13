<?php

namespace App\Mail;

use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PedidoConfirmacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public Pedido  $pedido;
    public array   $detalles;
    public string  $storeUrl;

    public function __construct(Pedido $pedido)
    {
        $this->pedido   = $pedido;
        $this->storeUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');

        // Cargar imágenes principales de los productos
        $pedido->loadMissing(['detalles.producto.imagenPrincipal']);

        $this->detalles = $pedido->detalles->map(function ($d) {
            $imagen = null;
            if ($d->producto && $d->producto->imagenPrincipal) {
                $imagen = $d->producto->imagenPrincipal->url_thumb
                       ?? $d->producto->imagenPrincipal->url
                       ?? null;
            }

            $url = null;
            if ($d->producto_id) {
                $slug = Str::slug($d->nombre_producto) . '-' . $d->producto_id;
                $url  = $this->storeUrl . '/productos/' . $slug;
            }

            return [
                'nombre_producto' => $d->nombre_producto,
                'cantidad'        => $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal'        => (float) $d->subtotal,
                'imagen'          => $imagen,
                'url'             => $url,
            ];
        })->toArray();
    }

    public function build(): static
    {
        return $this
            ->subject('Confirmación de pedido #' . $this->pedido->numero_pedido . ' — ' . config('app.name'))
            ->view('emails.pedido-confirmacion');
    }
}
