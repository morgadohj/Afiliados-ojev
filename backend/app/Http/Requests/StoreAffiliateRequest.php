<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'curp' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('curp'))),
            'postal_code' => preg_replace('/\D/', '', (string) $this->input('postal_code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'application_date' => ['required', 'date', 'before_or_equal:today'],
            'first_name' => ['required', 'string', 'max:120'],
            'paternal_last_name' => ['required', 'string', 'max:80'],
            'maternal_last_name' => ['nullable', 'string', 'max:80'],
            'curp' => [
                'required',
                'regex:/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/',
                Rule::unique('affiliates', 'curp'),
            ],
            'birth_date' => ['required', 'date', 'before:today'],
            'address_street' => ['required', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:150'],
            'locality' => ['required', 'string', 'max:150'],
            'municipality' => ['required', 'string', 'max:150'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'digits:5'],
            'home_phone' => ['nullable', 'string', 'max:25'],
            'mobile_phone' => ['required', 'string', 'max:25'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'occupation' => ['required', 'string', 'max:150'],
            'livestock_association' => ['nullable', 'string', 'max:180'],
            'oje_v_branch' => ['required', 'string', 'max:180'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'ine_front' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'ine_back' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'signature_name' => ['required', 'string', 'max:200'],
            'consent' => ['accepted'],
            'ocr_metadata' => ['nullable', 'json', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            'curp.regex' => 'La CURP debe contener 18 caracteres y tener un formato válido.',
            'curp.unique' => 'Ya existe una solicitud registrada con esta CURP.',
            'consent.accepted' => 'Debes aceptar la declaración de afiliación.',
            'ine_front.required' => 'La fotografía frontal de la INE es obligatoria.',
            'ine_back.required' => 'La fotografía posterior de la INE es obligatoria.',
        ];
    }
}
