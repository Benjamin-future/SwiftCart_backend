<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller will still re-check ownership/admin
    }

    public function rules(): array
    {
        return [
            'eta'           => ['required','date'],        // accepts many formats
            'note'          => ['nullable','string','max:255'],
            'address'       => ['nullable','string','max:255'],
            'contact_name'  => ['nullable','string','max:100'],
            'contact_phone' => ['nullable','string','max:30'],
            'lat'           => ['nullable','numeric'],
            'lng'           => ['nullable','numeric'],
        ];
    }
}

