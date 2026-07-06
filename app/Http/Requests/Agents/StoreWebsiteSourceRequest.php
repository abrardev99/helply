<?php

namespace App\Http\Requests\Agents;

use App\Rules\PublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreWebsiteSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('agent'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'url' => ['bail', 'required', 'string', 'max:2048', new PublicUrl],
        ];
    }
}
