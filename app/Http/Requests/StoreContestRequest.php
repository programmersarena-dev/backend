<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContestRequest extends FormRequest
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
        return [
            'type' => 'required|string',
            'name' => 'required|string',
            'authors' => 'present|array',
            'authors.*' => 'string|distinct|exists:users,handle',
            'start_date' => 'required|date|after:now',
            'duration' => 'required|date_format:H:i',
            'participants' => 'present|array',
            'participants.official' => 'present|array',
            'participants.unofficial' => 'present|array',
            'official' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
