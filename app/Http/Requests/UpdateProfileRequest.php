<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
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
    public function rules()
    {
        return [
            'old_password' => 'required_with:password|current_password',
            'password' => [
                'nullable',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()
            ],
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'country_id' => 'exists:countries,id',
            'image' => 'nullable|image|mimes:jpg,png,jpeg,gif|max:2048',
        ];
    }

    public function messages()
    {
        return [
            'old_password.required_with' => 'The old password is required when changing the password.',
            'old_password.current_password' => 'The old password is incorrect.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'The password must be at least 8 characters.',
            'first_name.required' => 'The first name field is required.',
            'last_name.required' => 'The last name field is required.',
            'country_id.required' => 'The country field is required.',
            'country_id.exists' => 'The selected country is invalid.',
            'image.image' => 'The image must be an image file.',
            'image.mimes' => 'The image must be a file of type: jpg, png, jpeg, gif.',
            'image.max' => 'The image must not be greater than 2048 kilobytes.',
        ];
    }
}
