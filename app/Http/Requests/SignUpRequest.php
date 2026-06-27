<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SignUpRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'handle' => [
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:32',
                'unique:users,handle'
            ],

            'first_name' => ['required', 'string', 'max:32'],
            'last_name' => ['required', 'string', 'max:32'],

            'email' => [
                'required',
                'string',
                'email',
                'max:128',
                'unique:users,email'
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],

            'country_id' => ['required', 'integer', 'exists:countries,id'],

            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,png,jpeg,gif',
                'max:2048'
            ],
        ];
    }
}
