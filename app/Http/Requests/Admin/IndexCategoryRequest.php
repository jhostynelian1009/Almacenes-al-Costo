<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCategoryRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'level' => ['nullable', Rule::in(['main', 'child'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');

        if (is_string($search)) {
            $search = preg_replace('/\s+/u', ' ', trim($search));
            $search = $search === '' ? null : $search;
        }

        $this->merge([
            'search' => $search,
            'status' => $this->input('status') === '' ? null : $this->input('status'),
            'parent_id' => $this->input('parent_id') === '' ? null : $this->input('parent_id'),
            'level' => $this->input('level') === '' ? null : $this->input('level'),
        ]);
    }
}
