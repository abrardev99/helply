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
    private const MAX_KILOBYTES = 20480;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('agent'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.self::MAX_KILOBYTES],
        ];
    }
}
