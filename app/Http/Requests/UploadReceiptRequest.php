<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Order ownership is verified in the controller via order reference
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'max:4096', 'mimes:jpeg,jpg,png,webp,pdf'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'in:transfer,deuna'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt.required' => 'Debe adjuntar el comprobante de pago.',
            'receipt.max' => 'El archivo no puede superar los 4 MB.',
            'receipt.mimes' => 'Solo se permiten archivos PDF, JPEG, PNG o WebP.',
            'payment_method.required' => 'Debe especificar el método de pago.',
            'payment_method.in' => 'Método de pago no válido.',
        ];
    }
}
