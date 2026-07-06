<?php

namespace App\Http\Requests\Agents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePdfRequest extends FormRequest
{
    /**
     * Largest PDF we accept, in kilobytes (20 MB).
     */
    private const MaxKilobytes = 20480;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('agent'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.self::MaxKilobytes],
        ];
    }
}
