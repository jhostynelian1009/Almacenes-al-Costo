<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('q');

        if (is_string($search)) {
            $search = preg_replace('/\s+/u', ' ', trim($search));
            $search = $search === '' ? null : $search;
        }

        $this->merge([
            'q' => $search,
            'category_id' => $this->input('category_id') === '' ? null : $this->input('category_id'),
            'status' => $this->input('status') === '' ? null : $this->input('status'),
        ]);
    }
}
