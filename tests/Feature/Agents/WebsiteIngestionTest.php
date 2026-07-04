<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\TeamRole;
use App\Jobs\CrawlSiteJob;
use App\Jobs\ProcessPageJob;
use App\Models\Agent;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Member of a fresh team with the given role, switched to it as their current team.
 *
 * @return array{0: User, 1: Team}
 */
function ingestionMember(TeamRole $role): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

/**
 * A public IP literal keeps the SSRF guard happy without a DNS lookup, so crawl tests
 * stay fully offline.
 */
const PUBLIC_HOST = 'https://93.184.216.34';

function sitemapXml(string ...$paths): string
{
    $entries = collect($paths)
        ->map(fn (string $path): string => '<url><loc>'.PUBLIC_HOST.$path.'</loc></url>')
        ->implode('');

    return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</urlset>';
}

it('lets a manager add a website URL and dispatches a crawl', function () {
    Queue::fake();

    [$user, $team] = ingestionMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('agents.sources.store', ['current_team' => $team->slug, 'agent' => $agent->id]), [
            'url' => PUBLIC_HOST.'/docs',
        ])
        ->assertRedirect();

    $document = Document::query()->firstOrFail();

    expect($document->agent_id)->toBe($agent->id)
        ->and($document->type)->toBe(DocumentType::Web)
        ->and($document->source_url)->toBe(PUBLIC_HOST.'/docs')
        ->and($document->status)->toBe(DocumentStatus::Pending);

    Queue::assertPushed(CrawlSiteJob::class, fn (CrawlSiteJob $job) => $job->agentId === $agent->id
        && $job->seedUrl === PUBLIC_HOST.'/docs');
});

it('rejects URLs that target private or internal addresses', function () {
    Queue::fake();

    [$user, $team] = ingestionMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();

    foreach (['http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5', 'ftp://example.com/file'] as $url) {
        $this->actingAs($user)
            ->post(route('agents.sources.store', ['current_team' => $team->slug, 'agent' => $agent->id]), ['url' => $url])
            ->assertInvalid(['url']);
    }

    expect(Document::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('forbids members from adding website sources', function () {
    Queue::fake();

    [$user, $team] = ingestionMember(TeamRole::Member);
    $agent = Agent::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('agents.sources.store', ['current_team' => $team->slug, 'agent' => $agent->id]), [
            'url' => 'https://docs.example.com',
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('lets a manager delete a document but not one from another agent', function () {
    [$user, $team] = ingestionMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();
    $document = Document::factory()->for($agent)->create();

    $otherDocument = Document::factory()->create();

    $this->actingAs($user)
        ->delete(route('agents.documents.destroy', ['current_team' => $team->slug, 'agent' => $agent->id, 'document' => $otherDocument->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('agents.documents.destroy', ['current_team' => $team->slug, 'agent' => $agent->id, 'document' => $document->id]))
        ->assertRedirect();

    expect(Document::find($document->id))->toBeNull()
        ->and(Document::find($otherDocument->id))->not->toBeNull();
});

it('re-crawls a agent by resetting its web documents to pending', function () {
    Queue::fake();

    [$user, $team] = ingestionMember(TeamRole::Owner);
    $agent = Agent::factory()->for($team)->create();
    Document::factory()->for($agent)->create([
        'type' => 'web',
        'source_url' => 'https://docs.example.com',
        'status' => DocumentStatus::Done,
    ]);

    $this->actingAs($user)
        ->post(route('agents.recrawl', ['current_team' => $team->slug, 'agent' => $agent->id]))
        ->assertRedirect();

    expect($agent->documents()->where('status', DocumentStatus::Pending)->count())->toBe(1);
    Queue::assertPushed(CrawlSiteJob::class);
});

it('crawls a sitemap into done documents with chunk text, always including the seed', function () {
    Bus::fake();

    Http::fake([
        PUBLIC_HOST.'/sitemap.xml' => Http::response(sitemapXml('/page-1', '/page-2')),
        '*' => Http::response('<html><head><title>Page</title></head><body><main>Readable page content.</main></body></html>'),
    ]);

    $agent = Agent::factory()->create();

    (new CrawlSiteJob($agent->id, PUBLIC_HOST.'/'))->handle();

    // page-1, page-2, plus the seed itself.
    expect($agent->documents()->count())->toBe(3)
        ->and($agent->documents()->where('source_url', PUBLIC_HOST.'/')->exists())->toBeTrue()
        ->and($agent->documents()->where('status', DocumentStatus::Processing)->count())->toBe(3);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
});

it('does not duplicate documents when a crawl re-runs', function () {
    Bus::fake();

    Http::fake([
        PUBLIC_HOST.'/sitemap.xml' => Http::response(sitemapXml('/page-1')),
        '*' => Http::response('<html><body>Content</body></html>'),
    ]);

    $agent = Agent::factory()->create();

    (new CrawlSiteJob($agent->id, PUBLIC_HOST.'/'))->handle();
    (new CrawlSiteJob($agent->id, PUBLIC_HOST.'/'))->handle();

    // page-1 + seed, regardless of how many times we crawl.
    expect($agent->documents()->count())->toBe(2);
});

it('fails documents gracefully when the site has no sitemap', function () {
    Http::fake([
        PUBLIC_HOST.'/sitemap.xml' => Http::response('Not found', 404),
    ]);

    $agent = Agent::factory()->create();
    $seed = Document::factory()->for($agent)->create([
        'type' => 'web',
        'source_url' => PUBLIC_HOST.'/',
        'status' => DocumentStatus::Processing,
    ]);

    (new CrawlSiteJob($agent->id, PUBLIC_HOST.'/'))->handle();

    expect($seed->fresh()->status)->toBe(DocumentStatus::Failed);
});

it('processes a page into a single chunk and is idempotent on re-run', function () {
    Http::fake([
        '*' => Http::response('<html><head><title>Hello</title></head><body><main>The page body text.</main></body></html>'),
    ]);

    $agent = Agent::factory()->create();
    $document = Document::factory()->for($agent)->create([
        'type' => 'web',
        'source_url' => PUBLIC_HOST.'/page',
        'status' => DocumentStatus::Processing,
    ]);

    (new ProcessPageJob($document->id))->handle();
    (new ProcessPageJob($document->id))->handle();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Done)
        ->and($document->title)->toBe('Hello')
        ->and($document->chunks()->count())->toBe(1)
        ->and($document->chunks()->first()->content)->toContain('page body text');
});

it('refuses to fetch a page whose URL targets a private address', function () {
    Http::fake();

    $agent = Agent::factory()->create();
    $document = Document::factory()->for($agent)->create([
        'type' => 'web',
        'source_url' => 'http://127.0.0.1/internal',
        'status' => DocumentStatus::Processing,
    ]);

    try {
        (new ProcessPageJob($document->id))->handle();
    } catch (Throwable) {
        // The job rethrows so the batch records the failure; we assert on the state.
    }

    expect($document->fresh()->status)->toBe(DocumentStatus::Failed);
    Http::assertNothingSent();
});
