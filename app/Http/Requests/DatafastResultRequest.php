<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatafastResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resourcePath' => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resourcePath.required' => 'No se recibio la referencia segura de Datafast.',
            'resourcePath.string' => 'La referencia de Datafast no es valida.',
            'resourcePath.max' => 'La referencia de Datafast no es valida.',
        ];
    }
}
