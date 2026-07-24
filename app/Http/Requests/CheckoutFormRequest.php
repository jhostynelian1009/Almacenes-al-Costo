<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutFormRequest extends FormRequest
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
        $isHomeDelivery = $this->input('delivery_method') === Order::DELIVERY_HOME;

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_identification' => ['required', 'string', 'max:30'],
            'delivery_method' => ['required', Rule::in([
                Order::DELIVERY_STORE_PICKUP,
                Order::DELIVERY_HOME,
            ])],
            'billing_province' => ['required', 'string', 'max:255'],
            'billing_city' => ['required', 'string', 'max:255'],
            'billing_address' => ['required', 'string', 'max:500'],
            'province' => [
                Rule::requiredIf($isHomeDelivery),
                'nullable',
                'string',
                'max:255',
            ],
            'city' => [
                Rule::requiredIf($isHomeDelivery),
                'nullable',
                'string',
                'max:255',
            ],
            'address' => [
                Rule::requiredIf($isHomeDelivery),
                'nullable',
                'string',
                'max:500',
            ],
            'delivery_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'subtotal' => ['prohibited'],
            'total' => ['prohibited'],
            'shipping_cost' => ['prohibited'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'El nombre completo es obligatorio.',
            'customer_email.required' => 'El correo electrónico es obligatorio.',
            'customer_email.email' => 'Ingresa un correo electrónico válido.',
            'customer_phone.required' => 'El teléfono es obligatorio.',
            'customer_identification.required' => 'La identificacion es obligatoria.',
            'billing_province.required' => 'La provincia de facturacion es obligatoria.',
            'billing_city.required' => 'La ciudad de facturacion es obligatoria.',
            'billing_address.required' => 'La direccion de facturacion es obligatoria.',
            'delivery_method.required' => 'Selecciona un método de entrega.',
            'delivery_method.in' => 'Selecciona un método de entrega válido.',
            'province.required' => 'La provincia es obligatoria para entrega a domicilio.',
            'city.required' => 'La ciudad es obligatoria para entrega a domicilio.',
            'address.required' => 'La dirección es obligatoria para entrega a domicilio.',
        ];
    }
}
