<?php

namespace App\Rules;

use App\Support\SafeUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PublicUrl implements ValidationRule
{
    /**
     * Accepts only a full http(s) URL whose host resolves to a public address, blocking
     * private/loopback/link-local targets to prevent SSRF against internal services.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SafeUrl::isPublic($value)) {
            $fail(__('The :attribute must be a valid, publicly reachable http or https URL.'));
        }
    }
}
