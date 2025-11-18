<?php

namespace Backpack\Profile\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Авторизация проверяется через middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'type' => 'string|in:credit,debit,hold,release,capture',
            'reference_type' => 'string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.integer' => 'Количество записей на страницу должно быть числом.',
            'per_page.min' => 'Минимальное количество записей на страницу: 1.',
            'per_page.max' => 'Максимальное количество записей на страницу: 100.',
            'page.integer' => 'Номер страницы должен быть числом.',
            'page.min' => 'Номер страницы должен быть больше 0.',
            'type.in' => 'Недопустимый тип операции. Доступные: credit, debit, hold, release, capture.',
            'reference_type.string' => 'Тип ссылки должен быть строкой.',
            'reference_type.max' => 'Тип ссылки не может быть длиннее 255 символов.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'per_page' => 'количество записей на страницу',
            'page' => 'номер страницы',
            'type' => 'тип операции',
            'reference_type' => 'тип ссылки',
        ];
    }
}