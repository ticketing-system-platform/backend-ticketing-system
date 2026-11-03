<?php

namespace App\Http\Requests\Admin;

use App\Models\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TicketTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0'],
            'quota' => ['required', 'integer', 'min:1'],
        ];

        if ($this->isMethod('post')) {
            $rules['event_id'] = ['required', 'exists:events,id'];
        }

        return $rules;
    }

    /**
     * Validasi tambahan setelah rules di atas:
     * Kuota tidak boleh lebih kecil dari tiket yang sudah dijual
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ticketType = $this->route('ticket_type');

                if (is_numeric($ticketType)) {
                    $ticketType = TicketType::find($ticketType);
                }

                if ($ticketType instanceof TicketType && $this->filled('quota')) {
                    if ((int) $this->input('quota') < $ticketType->sold) {
                        $validator->errors()->add(
                            'quota',
                            "Kuota tidak boleh kurang dari tiket yang sudah terjual ({$ticketType->sold})"
                        );
                    }
                }
            },
        ];
    }
}
