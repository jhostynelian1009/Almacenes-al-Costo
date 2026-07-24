<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptController extends Controller
{
    /**
     * Stream the receipt file for an order to the authenticated admin.
     * Prevents path traversal and never exposes raw file paths.
     */
    public function download(int $orderId): Response|StreamedResponse
    {
        $order = Order::query()
            ->with(['receipt'])
            ->findOrFail($orderId);

        $receipt = $order->receipt;

        if ($receipt === null) {
            abort(404, 'No se encontró un comprobante para este pedido.');
        }

        $path = $receipt->file_path;

        // Path traversal protection: ensure normalized path starts with comprobantes/ and has no relative directory steps
        $normalizedPath = str_replace('\\', '/', $path);
        if (str_contains($normalizedPath, '..') || ! str_starts_with($normalizedPath, 'comprobantes/')) {
            abort(403, 'Acceso denegado.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'Archivo de comprobante no encontrado.');
        }

        return Storage::disk('local')->response($path, $receipt->original_filename ?? basename($path));
    }
}
