<?php

namespace App\Services;

use App\Exceptions\PaymentOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptUploadService
{
    /**
     * Allowed MIME types for receipts.
     */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /**
     * Maximum file size in bytes (4 MB per SPEC).
     */
    private const MAX_SIZE_BYTES = 4 * 1024 * 1024;

    /**
     * Private storage directory.
     */
    private const STORAGE_DIRECTORY = 'comprobantes';

    /**
     * Validate and store a payment receipt file securely.
     *
     * @throws PaymentOperationException on invalid file
     */
    public function store(
        UploadedFile $file,
        Order $order,
        string $paymentMethod,
        ?string $reference = null,
        ?Payment $payment = null,
    ): PaymentReceipt {
        $this->validateFile($file);

        $storedPath = $this->storeFile($file, $order);

        $receipt = PaymentReceipt::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_id' => $payment?->id,
                'file_path' => $storedPath,
                'original_filename' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                'payment_method' => $paymentMethod,
                'transaction_reference' => $reference,
                'rejection_reason' => null,
                'uploaded_at' => now(),
            ],
        );

        Log::info('Receipt uploaded', [
            'order_reference' => $order->reference,
            'payment_method' => $paymentMethod,
            'receipt_id' => $receipt->id,
        ]);

        return $receipt;
    }

    /**
     * Validate MIME type and size of an uploaded file.
     *
     * @throws PaymentOperationException
     */
    public function validateFile(UploadedFile $file): void
    {
        // Validate MIME from actual file content (not extension)
        $mimeType = $file->getMimeType();
        if ($mimeType === null || ! in_array($mimeType, self::ALLOWED_MIMES, true)) {
            throw PaymentOperationException::invalidReceipt(
                'Solo se permiten archivos PDF, JPEG, PNG o WebP.'
            );
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw PaymentOperationException::invalidReceipt(
                'El archivo no puede superar los 4 MB.'
            );
        }
    }

    /**
     * Store file in private storage with a generated, safe filename.
     * Never trusts the client-provided extension alone.
     */
    private function storeFile(UploadedFile $file, Order $order): string
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $extension = $this->extensionFromMime($mimeType);

        // Generated filename: order_reference + random hash
        $safeFilename = Str::slug($order->reference).'_'.Str::random(16).'.'.$extension;

        $path = self::STORAGE_DIRECTORY.'/'.$safeFilename;

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
