<?php

namespace App\Http\Requests\Agents;

use App\Rules\HasSitemap;
use App\Rules\PublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreWebsiteSourceRequest extends FormRequest
{
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
        // `bail` stops at the first failure so we only fetch the sitemap for a URL that is
        // already a valid, public http(s) address.
        return [
            'url' => ['bail', 'required', 'string', 'max:2048', new PublicUrl, new HasSitemap],
        ];
    }
}
