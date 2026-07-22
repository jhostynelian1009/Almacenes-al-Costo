<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductImageService
{
    private const DIRECTORY = 'products';

    public function store(UploadedFile $image): string
    {
        $path = $image->store(self::DIRECTORY, 'public');

        if (! is_string($path) || ! $this->isManagedPath($path)) {
            throw new RuntimeException('No se pudo almacenar la imagen del producto.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $this->isManagedPath($path)) {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($path) && ! $disk->delete($path)) {
            throw new RuntimeException('No se pudo eliminar la imagen del producto.');
        }
    }

    public function exists(?string $path): bool
    {
        return $this->isManagedPath($path)
            && Storage::disk('public')->exists($path);
    }

    public function url(?string $path): ?string
    {
        if (! $this->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function isManagedPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('/\Aproducts\/[^\/\\\\]+\z/u', $path) === 1;
    }
}
