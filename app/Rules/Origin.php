<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Origin implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * An origin is a bare scheme + host (+ optional port) with no path,
     * query, fragment, or credentials — e.g. `https://app.example.com`.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ($parts = parse_url($value)) === false) {
            $fail(__('The :attribute must be a valid origin like https://example.com.'));

            return;
        }

        if (! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $fail(__('The :attribute must be a valid origin like https://example.com.'));

            return;
        }

        $hasPathOrCredentials = (isset($parts['path']) && $parts['path'] !== '')
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass']);

        if ($hasPathOrCredentials) {
            $fail(__('The :attribute must be a bare origin without a path or query.'));
        }
    }
}
