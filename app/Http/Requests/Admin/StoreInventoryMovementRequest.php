<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true && $this->user()->role === UserRole::Admin;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(InventoryMovement::validTypes())],
            'quantity' => ['exclude_if:type,adjustment', 'required', 'integer', 'min:1'],
            'new_stock' => ['exclude_unless:type,adjustment', 'required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        $reason = $this->input('reason');
        $idempotencyKey = $this->input('idempotency_key');

        $this->merge([
            'type' => is_string($type) ? trim($type) : $type,
            'quantity' => $this->normalizeInteger($this->input('quantity')),
            'new_stock' => $this->normalizeInteger($this->input('new_stock')),
            'reason' => is_string($reason) ? trim($reason) : $reason,
            'idempotency_key' => is_string($idempotencyKey) ? trim($idempotencyKey) : $idempotencyKey,
        ]);
    }

    private function normalizeInteger(mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/\A[+-]?\d+\z/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }
}
