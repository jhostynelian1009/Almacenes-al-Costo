<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $category = $this->category();

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string'],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                Rule::notIn([$category->getKey()]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $category = $this->category();
        $name = $this->input('name');
        $description = $this->input('description');

        if (is_string($name)) {
            $name = preg_replace('/\s+/u', ' ', trim($name));
        }

        if (is_string($description)) {
            $description = trim($description);
            $description = $description === '' ? null : $description;
        }

        $slug = is_string($name) && $name !== $category->name
            ? Str::slug($name)
            : $category->slug;

        $this->merge([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $this->input('parent_id') === '' ? null : $this->input('parent_id'),
        ]);
    }

    private function category(): Category
    {
        $category = $this->route('category');

        abort_unless($category instanceof Category, 404);

        return $category;
    }
}
