<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChatMessageRequest extends FormRequest
{
    /**
     * The public widget endpoint is unauthenticated; origin + rate limiting guard it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'string', 'max:1024'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
