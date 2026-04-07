<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImgBBService
{
    protected ?string $apiKey;
    protected ?string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.imgbb.api_key');
        $this->baseUrl = config('services.imgbb.base_url');
    }

    public function uploadImage(UploadedFile $image, ?string $name = null): array
    {
        try {
            $base64Image = base64_encode(file_get_contents($image->getRealPath()));

            $data = [
                'key'   => $this->apiKey,
                'image' => $base64Image,
                'name'  => $name ?? $image->getClientOriginalName(),
            ];

            if (config('services.imgbb.expiration')) {
                $data['expiration'] = config('services.imgbb.expiration');
            }

            Log::info('Enviando imagen a ImgBB', ['name' => $data['name'], 'size' => $image->getSize(), 'type' => $image->getMimeType()]);

            $http = Http::timeout(30)->retry(3, 1000)->asForm();

            if (app()->isLocal()) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post($this->baseUrl, $data);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Imagen subida exitosamente a ImgBB', [
                    'id'    => $result['data']['id']  ?? null,
                    'url'   => $result['data']['url'] ?? null,
                    'thumb' => $result['data']['thumb']['url'] ?? null,
                ]);

                return ['success' => true, 'data' => $result['data']];
            }

            Log::error('Error en respuesta de ImgBB', ['status' => $response->status(), 'body' => $response->body()]);

            return ['success' => false, 'error' => 'Error en la API de ImgBB: ' . $response->status(), 'details' => $response->json()];

        } catch (\Exception $e) {
            Log::error('Excepción al subir imagen a ImgBB', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    public function uploadMultipleImages(array $images): array
    {
        $results = [];
        foreach ($images as $key => $image) {
            if ($image instanceof UploadedFile) {
                $results[] = [
                    'index'         => $key,
                    'original_name' => $image->getClientOriginalName(),
                    'result'        => $this->uploadImage($image, $image->getClientOriginalName()),
                ];
            }
        }
        return $results;
    }

    public function deleteImage(string $deleteUrl): array
    {
        return ['success' => false, 'message' => 'La eliminación requiere cuenta premium de ImgBB'];
    }
}
