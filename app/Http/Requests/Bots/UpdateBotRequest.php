<?php

namespace App\Http\Requests\Bots;

use App\Enums\BotStatus;
use App\Rules\Origin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateBotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('bot'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'embed_origins' => ['nullable', 'array'],
            'embed_origins.*' => ['string', new Origin],
            'status' => ['sometimes', Rule::enum(BotStatus::class)],
            'embedding_model' => ['sometimes', 'required', 'string', 'max:255'],
            'chat_model' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'confidence_threshold' => ['sometimes', 'required', 'numeric', 'between:0,1'],
        ];
    }
}
