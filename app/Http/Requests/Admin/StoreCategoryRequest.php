<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $description = $this->input('description');

        if (is_string($name)) {
            $name = preg_replace('/\s+/u', ' ', trim($name));
        }

        if (is_string($description)) {
            $description = trim($description);
            $description = $description === '' ? null : $description;
        }

        $this->merge([
            'name' => $name,
            'slug' => is_string($name) ? Str::slug($name) : null,
            'description' => $description,
            'parent_id' => $this->input('parent_id') === '' ? null : $this->input('parent_id'),
        ]);
    }
}
