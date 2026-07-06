<?php

namespace App\Http\Requests\Agents;

use App\Models\Agent;
use App\Rules\Origin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Agent::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'embed_origins' => ['nullable', 'array'],
            'embed_origins.*' => ['string', new Origin],
        ];
    }
}
