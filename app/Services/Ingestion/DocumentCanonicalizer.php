<?php

namespace App\Services\Ingestion;

use App\Models\Document;
use App\Support\SafeUrl;

class DocumentCanonicalizer
{
    /**
     * Re-key a document to the URL its fetch actually landed on.
     *
     * A crawl reaches the same page under several URLs — an apex host that 308s to `www`,
     * a sitemap that lists one variant while link-following discovers another. Keying a
     * document on the *requested* URL stores that one page once per variant, so its text
     * is embedded (and later retrieved) several times over. Keying on the *final* URL
     * collapses every redirect-equivalent variant onto one row without having to guess
     * which host or path form is canonical.
     *
     * @return Document The document that should own this page's content: either the one
     *                  passed in (re-keyed when the fetch redirected) or the pre-existing
     *                  document already holding the final URL, in which case the caller's
     *                  duplicate row is deleted.
     */
    public function canonicalize(Document $document, string $finalUrl): Document
    {
        $canonical = SafeUrl::normalize($finalUrl);

        if ($canonical === $document->source_url) {
            return $document;
        }

        $existing = Document::query()
            ->where('agent_id', $document->agent_id)
            ->where('source_url', $canonical)
            ->whereKeyNot($document->getKey())
            ->first();

        if ($existing === null) {
            $document->update(['source_url' => $canonical]);

            return $document;
        }

        // Another row already represents this page. Drop the duplicate — its chunks cascade
        // — and let the established document own the content the caller is about to store.
        $document->delete();

        return $existing;
    }
}
