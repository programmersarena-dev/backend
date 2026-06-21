<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitProblemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $contest = $this->route('contest');
        $code = $this->input('code');
        $file = $this->file('file');
        $language = $this->input('language');
        $char = $this->route('char');

        if (!$contest) {
            abort(404, 'Bäsleşik tapylmady');
        }

        if ($contest->getStatus() === 'notStarted') {
            abort(403, 'Bäsleşik başlamady');
        }

        if (!$contest->canUserSubmit($user->id)) {
            abort(403, 'Size şu wagt meseläniň çözüwini ugratmaga rugsat berilmeýär');
        }

        $problem = $contest->getProblemByCharacter($char);

        if (!$problem) {
            abort(404, 'Mesele tapylmady');
        }

        if (!in_array($language, $problem->acceptableLanguages())) {
            abort(403, 'Dil näsazlygy');
        }

        if (!$file && !$code) {
            abort(400, 'Kod tapylmady');
        }

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
            'code' => 'nullable|max:512000',
            'file' => 'nullable|file|max:512000',
            'language' => 'required',
        ];
    }
}
