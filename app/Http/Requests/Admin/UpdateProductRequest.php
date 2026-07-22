<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($this->product())],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $sku = $this->input('sku');
        $description = $this->input('description');

        if (is_string($name)) {
            $name = preg_replace('/\s+/u', ' ', trim($name));
        }

        if (is_string($sku)) {
            $sku = trim($sku);
        }

        if (is_string($description)) {
            $description = trim($description);
            $description = $description === '' ? null : $description;
        }

        $this->merge([
            'name' => $name,
            'sku' => $sku,
            'description' => $description,
        ]);
    }

    private function product(): Product
    {
        $product = $this->route('product');

        abort_unless($product instanceof Product, 404);

        return $product;
    }
}
