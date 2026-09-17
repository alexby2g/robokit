<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaStorage
{
    public function publicDiskName(): string
    {
        return (string) config('filesystems.robokit.public_disk', 'public');
    }

    public function privateDiskName(): string
    {
        return (string) config('filesystems.robokit.private_disk', 'local');
    }

    public function publicBaseUrl(): ?string
    {
        $url = trim((string) config('filesystems.robokit.public_url', ''));
        return $url !== '' ? rtrim($url, '/') : null;
    }

    public function usesRemotePublicStorage(): bool
    {
        return $this->publicDiskName() !== 'public';
    }

    public function normalizePath(?string $path): ?string
    {
        if ($path === null) return null;

        $value = trim(str_replace('\\', '/', $path));
        if ($value === '') return null;
        if (preg_match('/^https?:\/\//i', $value)) return $value;

        return ltrim($value, '/');
    }

    public function publicUrl(?string $path): ?string
    {
        $path = $this->normalizePath($path);
        if (!$path) return null;
        if (preg_match('/^https?:\/\//i', $path)) return $path;

        if ($base = $this->publicBaseUrl()) {
            return $base.'/'.implode('/', array_map('rawurlencode', explode('/', $path)));
        }

        try {
            return Storage::disk($this->publicDiskName())->url($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function storePublic(UploadedFile $file, string $directory): string
    {
        return $this->storePublicAs($file, $directory, $file->hashName());
    }

    public function storePublicAs(UploadedFile $file, string $directory, string $filename): string
    {
        $path = Storage::disk($this->publicDiskName())->putFileAs($directory, $file, $filename);
        if (!$path) throw new RuntimeException('No se pudo guardar el archivo público.');
        return $path;
    }

    public function storePrivate(UploadedFile $file, string $directory): string
    {
        $path = Storage::disk($this->privateDiskName())->putFile($directory, $file);
        if (!$path) throw new RuntimeException('No se pudo guardar el archivo privado.');
        return $path;
    }

    public function deletePublic(?string $path): void
    {
        $path = $this->normalizePath($path);
        if (!$path || preg_match('/^https?:\/\//i', $path)) return;

        try {
            Storage::disk($this->publicDiskName())->delete($path);
        } catch (\Throwable) {
            // La eliminación de archivo no debe romper el registro principal.
        }
    }

    public function deletePrivate(?string $path): void
    {
        $path = $this->normalizePath($path);
        if (!$path || preg_match('/^https?:\/\//i', $path)) return;

        try {
            Storage::disk($this->privateDiskName())->delete($path);
        } catch (\Throwable) {
            // Compatibilidad: si el archivo ya no existe, no fallamos.
        }
    }

    public function publicExists(string $path): bool
    {
        $path = $this->normalizePath($path) ?? '';
        return $path !== '' && !preg_match('/^https?:\/\//i', $path)
            && Storage::disk($this->publicDiskName())->exists($path);
    }

    /**
     * Obtiene un comprobante privado. Como compatibilidad con v5.5,
     * busca también en el almacenamiento público antiguo.
     *
     * @return array{contents:string,mime:string,filename:string}
     */
    public function privateFile(string $path): array
    {
        $path = $this->normalizePath($path) ?? '';
        if ($path === '' || preg_match('/^https?:\/\//i', $path)) {
            throw new RuntimeException('Ruta de archivo inválida.');
        }

        foreach ([$this->privateDiskName(), $this->publicDiskName()] as $diskName) {
            $disk = Storage::disk($diskName);
            if (!$disk->exists($path)) continue;

            $mime = 'application/octet-stream';
            try {
                $mime = $disk->mimeType($path) ?: $mime;
            } catch (\Throwable) {
                // MIME genérico como respaldo.
            }

            return [
                'contents' => $disk->get($path),
                'mime' => $mime,
                'filename' => basename($path),
            ];
        }

        throw new RuntimeException('Archivo no encontrado.');
    }
}
