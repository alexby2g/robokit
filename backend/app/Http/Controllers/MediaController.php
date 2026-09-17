<?php

namespace App\Http\Controllers;

use App\Services\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(private MediaStorage $media) {}

    public function show(string $path): Response
    {
        $path = rawurldecode($path);
        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_if($path === '' || str_contains($path, '..'), 404);

        // En producción no hacemos pasar cada imagen por Render: redirigimos
        // al bucket público de R2 para aprovechar su CDN/egress.
        if ($this->media->usesRemotePublicStorage() && $this->media->publicBaseUrl()) {
            $url = $this->media->publicUrl($path);
            abort_unless($url, 404, 'Imagen no encontrada.');

            return redirect()->away($url, 302, [
                'Cache-Control' => 'public, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Desarrollo local: seguimos sirviendo desde storage/app/public.
        $disk = Storage::disk($this->media->publicDiskName());
        abort_unless($disk->exists($path), 404, 'Imagen no encontrada.');

        $mime = 'application/octet-stream';
        try {
            $mime = $disk->mimeType($path) ?: $mime;
        } catch (\Throwable) {
            // MIME genérico de respaldo.
        }

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
