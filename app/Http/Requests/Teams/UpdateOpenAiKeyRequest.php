<?php

namespace App\Http\Requests\Teams;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateOpenAiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('team'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'openai_api_key' => ['required', 'string', 'max:255', 'starts_with:sk-'],
        ];
    }
}
