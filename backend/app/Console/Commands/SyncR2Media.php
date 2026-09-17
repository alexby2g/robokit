<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SyncR2Media extends Command
{
    protected $signature = 'robokit:r2-sync {--force : Sobrescribe archivos que ya existen en R2}';

    protected $description = 'Copia imágenes y comprobantes locales de ROBOKIT a Cloudflare R2 sin cambiar las rutas guardadas en la base.';

    public function handle(): int
    {
        if (!config('filesystems.disks.r2_public.bucket') || !config('filesystems.disks.r2_private.bucket')) {
            $this->error('Faltan AWS_PUBLIC_BUCKET/AWS_PRIVATE_BUCKET en el .env local.');
            return self::FAILURE;
        }

        $publicPaths = collect();
        if (Schema::hasTable('imagenes')) {
            $publicPaths = $publicPaths->merge(DB::table('imagenes')->whereNotNull('ruta')->pluck('ruta'));
        }
        if (Schema::hasTable('cursos')) {
            $publicPaths = $publicPaths->merge(DB::table('cursos')->whereNotNull('imagen')->pluck('imagen'));
        }

        $publicPaths = $publicPaths
            ->filter(fn ($path) => is_string($path) && $path !== '' && !preg_match('/^https?:\/\//i', $path))
            ->unique()
            ->values();

        $privatePaths = collect();
        if (Schema::hasTable('pagos')) {
            $privatePaths = DB::table('pagos')
                ->whereNotNull('comprobante')
                ->pluck('comprobante')
                ->filter(fn ($path) => is_string($path) && $path !== '' && !preg_match('/^https?:\/\//i', $path))
                ->unique()
                ->values();
        }

        $this->info('Sincronizando archivos públicos: '.$publicPaths->count());
        $public = $this->syncPaths($publicPaths->all(), 'public', 'r2_public');

        $this->info('Sincronizando comprobantes privados: '.$privatePaths->count());
        $private = $this->syncPaths($privatePaths->all(), 'public', 'r2_private');

        $this->newLine();
        $this->table(
            ['Destino', 'Subidos', 'Omitidos', 'No encontrados', 'Errores'],
            [
                ['robokit-public', $public['uploaded'], $public['skipped'], $public['missing'], $public['errors']],
                ['robokit-private', $private['uploaded'], $private['skipped'], $private['missing'], $private['errors']],
            ],
        );

        if (($public['errors'] + $private['errors']) > 0) {
            $this->warn('La sincronización terminó con errores. Revisa las credenciales y rutas indicadas arriba.');
            return self::FAILURE;
        }

        $this->info('R2 sincronizado. Las rutas de Neon/MySQL no necesitan cambiar.');
        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $paths
     * @return array{uploaded:int,skipped:int,missing:int,errors:int}
     */
    private function syncPaths(array $paths, string $sourceDisk, string $targetDisk): array
    {
        $stats = ['uploaded' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => 0];
        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);

        foreach ($paths as $path) {
            $path = ltrim(str_replace('\\', '/', $path), '/');

            try {
                if (!$source->exists($path)) {
                    $stats['missing']++;
                    $this->line("  - NO ENCONTRADO: {$path}");
                    continue;
                }

                if (!$this->option('force') && $target->exists($path)) {
                    $stats['skipped']++;
                    $this->line("  = YA EXISTE: {$path}");
                    continue;
                }

                $stream = $source->readStream($path);
                if (!is_resource($stream)) {
                    throw new \RuntimeException('No se pudo abrir el archivo local.');
                }

                try {
                    $ok = $target->put($path, $stream);
                } finally {
                    fclose($stream);
                }

                if (!$ok) throw new \RuntimeException('R2 rechazó la carga.');

                $stats['uploaded']++;
                $this->line("  + SUBIDO: {$path}");
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->error("  ! ERROR {$path}: {$e->getMessage()}");
            }
        }

        return $stats;
    }
}
