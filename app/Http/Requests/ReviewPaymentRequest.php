<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth + admin middleware handles authorization
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Debe especificar la acción a realizar.',
            'action.in' => 'La acción debe ser "approve" o "reject".',
            'reason.required_if' => 'Debe proporcionar el motivo del rechazo.',
        ];
    }
}
