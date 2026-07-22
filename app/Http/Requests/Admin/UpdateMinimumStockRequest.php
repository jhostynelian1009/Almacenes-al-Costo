<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMinimumStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active === true && $this->user()->role === UserRole::Admin;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'min_stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $minimumStock = $this->input('min_stock');

        if (is_string($minimumStock)) {
            $minimumStock = trim($minimumStock);

            if (preg_match('/\A\d+\z/', $minimumStock) === 1) {
                $minimumStock = (int) $minimumStock;
            }
        }

        $this->merge(['min_stock' => $minimumStock]);
    }
}
