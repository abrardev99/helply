<?php

namespace App\Http\Requests\Agents;

use App\Enums\AgentStatus;
use App\Rules\Origin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('agent'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'embed_origins' => ['nullable', 'array'],
            'embed_origins.*' => ['string', new Origin],
            'status' => ['sometimes', Rule::enum(AgentStatus::class)],
            'embedding_model' => ['sometimes', 'required', 'string', 'max:255'],
            'chat_model' => ['sometimes', 'required', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'confidence_threshold' => ['sometimes', 'required', 'numeric', 'between:0,1'],
        ];
    }
}
