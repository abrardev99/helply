<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;
use Illuminate\Translation\PotentiallyTranslatedString;

class HasSitemap implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Ingestion enumerates a site's public pages from its sitemap, so a URL is only
     * accepted when `<scheme>://<host>/sitemap.xml` is reachable. Assumes the value is a
     * valid public URL already (validate the PublicUrl rule first, with `bail`).
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('The :attribute must be a valid URL.'));

            return;
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->retry(1, 300, throw: false)
            ->get($this->sitemapUrlFor($value));

        if ($response->failed()) {
            $fail(__('This site has no reachable sitemap.xml. Add a sitemap so the agent can crawl its public pages.'));
        }
    }

    /**
     * Build the sitemap.xml URL for the given URL's scheme + host (+ optional port).
     */
    private function sitemapUrlFor(string $url): string
    {
        $uri = Uri::of($url);
        $authority = $uri->host().($uri->port() !== null ? ':'.$uri->port() : '');

        return ($uri->scheme() ?: 'https').'://'.$authority.'/sitemap.xml';
    }
}
