<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\Document;
use App\Services\Ingestion\DocumentCanonicalizer;

/**
 * A web document for the given agent at the given URL.
 */
function canonicalizerDocument(Agent $agent, string $url, DocumentStatus $status = DocumentStatus::Processing): Document
{
    return Document::factory()->for($agent)->create([
        'type' => DocumentType::Web,
        'source_url' => $url,
        'status' => $status,
    ]);
}

it('leaves a document alone when the fetch did not redirect', function () {
    $agent = Agent::factory()->create();
    $document = canonicalizerDocument($agent, 'https://site.test/uses');

    $result = (new DocumentCanonicalizer)->canonicalize($document, 'https://site.test/uses');

    expect($result->id)->toBe($document->id)
        ->and($result->source_url)->toBe('https://site.test/uses')
        ->and($agent->documents()->count())->toBe(1);
});

it('re-keys a document to the URL the fetch landed on', function () {
    $agent = Agent::factory()->create();
    $document = canonicalizerDocument($agent, 'https://site.test/uses');

    $result = (new DocumentCanonicalizer)->canonicalize($document, 'https://www.site.test/uses');

    expect($result->id)->toBe($document->id)
        ->and($result->fresh()->source_url)->toBe('https://www.site.test/uses')
        ->and($agent->documents()->count())->toBe(1);
});

it('collapses an apex duplicate into the existing www document', function () {
    $agent = Agent::factory()->create();
    $canonical = canonicalizerDocument($agent, 'https://www.site.test/uses', DocumentStatus::Done);
    $duplicate = canonicalizerDocument($agent, 'https://site.test/uses');

    $result = (new DocumentCanonicalizer)->canonicalize($duplicate, 'https://www.site.test/uses');

    // The established document wins and the duplicate row is gone.
    expect($result->id)->toBe($canonical->id)
        ->and(Document::find($duplicate->id))->toBeNull()
        ->and($agent->documents()->count())->toBe(1);
});

it('deletes the duplicate document chunks when collapsing', function () {
    $agent = Agent::factory()->create();
    canonicalizerDocument($agent, 'https://www.site.test/uses', DocumentStatus::Done);
    $duplicate = canonicalizerDocument($agent, 'https://site.test/uses');

    Chunk::factory()->for($duplicate)->create();

    expect(Chunk::count())->toBe(1);

    (new DocumentCanonicalizer)->canonicalize($duplicate, 'https://www.site.test/uses');

    expect(Chunk::count())->toBe(0);
});

it('normalizes the final URL before comparing', function () {
    $agent = Agent::factory()->create();
    $document = canonicalizerDocument($agent, 'https://site.test/articles');

    // Trailing slash and fragment are noise; this is the same page.
    $result = (new DocumentCanonicalizer)->canonicalize($document, 'https://site.test/articles/#top');

    expect($result->fresh()->source_url)->toBe('https://site.test/articles')
        ->and($agent->documents()->count())->toBe(1);
});

it('does not collapse into another agent document at the same URL', function () {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();

    canonicalizerDocument($otherAgent, 'https://www.site.test/uses', DocumentStatus::Done);
    $document = canonicalizerDocument($agent, 'https://site.test/uses');

    $result = (new DocumentCanonicalizer)->canonicalize($document, 'https://www.site.test/uses');

    // Tenants are isolated: the other agent's document is irrelevant here.
    expect($result->id)->toBe($document->id)
        ->and($otherAgent->documents()->count())->toBe(1)
        ->and($agent->documents()->count())->toBe(1);
});
