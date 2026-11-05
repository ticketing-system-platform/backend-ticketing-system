<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Contoh body:
     * { "items": [ { "ticket_type_id": 1, "qty": 2 }, { "ticket_type_id": 2, "qty": 1 } ] }
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            // distinct: jenis tiket yang sama tidak boleh muncul dua kali
            'items.*.ticket_type_id' => ['required', 'integer', 'distinct', 'exists:ticket_types,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
