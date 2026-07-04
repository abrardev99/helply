<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\TeamRole;
use App\Jobs\ImportPdfJob;
use App\Models\Bot;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Member of a fresh team with the given role, switched to it as their current team.
 *
 * @return array{0: User, 1: Team}
 */
function pdfMember(TeamRole $role): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

/**
 * Build a minimal but valid multi-page PDF with correct xref offsets so smalot/pdfparser
 * can extract the given per-page text — no binary fixture file required.
 *
 * @param  list<string>  $pageTexts
 */
function makePdf(array $pageTexts): string
{
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $kids = [];
    $id = 4;

    foreach ($pageTexts as $text) {
        $pageId = $id++;
        $contentId = $id++;
        $stream = "BT /F1 24 Tf 72 720 Td ({$text}) Tj ET";

        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentId} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
        $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        $kids[] = "{$pageId} 0 R";
    }

    $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pageTexts).' >>';

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $oid => $body) {
        $offsets[$oid] = strlen($pdf);
        $pdf .= "{$oid} 0 obj\n{$body}\nendobj\n";
    }

    $size = max(array_keys($objects)) + 1;
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

    for ($i = 1; $i < $size; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
}

it('lets a manager upload a PDF, storing it and queueing extraction', function () {
    Storage::fake('local');
    Queue::fake();

    [$user, $team] = pdfMember(TeamRole::Owner);
    $bot = Bot::factory()->for($team)->create();

    $file = UploadedFile::fake()->createWithContent('handbook.pdf', makePdf(['Hello world']));

    $this->actingAs($user)
        ->post(route('bots.pdfs.store', ['current_team' => $team->slug, 'bot' => $bot->id]), [
            'file' => $file,
        ])
        ->assertRedirect();

    $document = Document::query()->firstOrFail();

    expect($document->type)->toBe(DocumentType::Pdf)
        ->and($document->title)->toBe('handbook.pdf')
        ->and($document->status)->toBe(DocumentStatus::Pending);

    Storage::disk('local')->assertExists($document->source_url);
    Queue::assertPushed(ImportPdfJob::class, fn (ImportPdfJob $job) => $job->documentId === $document->id);
});

it('rejects non-PDF and oversized uploads', function () {
    Storage::fake('local');
    Queue::fake();

    [$user, $team] = pdfMember(TeamRole::Owner);
    $bot = Bot::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('bots.pdfs.store', ['current_team' => $team->slug, 'bot' => $bot->id]), [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertInvalid(['file']);

    $this->actingAs($user)
        ->post(route('bots.pdfs.store', ['current_team' => $team->slug, 'bot' => $bot->id]), [
            'file' => UploadedFile::fake()->create('huge.pdf', 30000, 'application/pdf'),
        ])
        ->assertInvalid(['file']);

    expect(Document::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('forbids members from uploading PDFs', function () {
    Storage::fake('local');
    Queue::fake();

    [$user, $team] = pdfMember(TeamRole::Member);
    $bot = Bot::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('bots.pdfs.store', ['current_team' => $team->slug, 'bot' => $bot->id]), [
            'file' => UploadedFile::fake()->createWithContent('handbook.pdf', makePdf(['Hello'])),
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('extracts PDF text into multiple chunks and is idempotent on re-run', function () {
    Storage::fake('local');

    $bot = Bot::factory()->create();
    Storage::disk('local')->put('pdfs/doc.pdf', makePdf(['First page body', 'Second page body']));

    $document = Document::factory()->for($bot)->create([
        'type' => DocumentType::Pdf,
        'source_url' => 'pdfs/doc.pdf',
        'title' => 'handbook.pdf',
        'status' => DocumentStatus::Pending,
    ]);

    (new ImportPdfJob($document->id))->handle();
    (new ImportPdfJob($document->id))->handle();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Done)
        ->and($document->chunks()->count())->toBe(2);

    $text = $document->chunks()->get()->pluck('content')->implode(' ');
    expect($text)->toContain('First page body')
        ->and($text)->toContain('Second page body');
});

it('marks a document failed when the PDF is unreadable', function () {
    Storage::fake('local');

    $bot = Bot::factory()->create();
    Storage::disk('local')->put('pdfs/broken.pdf', 'this is not a real pdf');

    $document = Document::factory()->for($bot)->create([
        'type' => DocumentType::Pdf,
        'source_url' => 'pdfs/broken.pdf',
        'status' => DocumentStatus::Pending,
    ]);

    try {
        (new ImportPdfJob($document->id))->handle();
    } catch (Throwable) {
        // Rethrown for the queue; assert on the persisted state.
    }

    expect($document->fresh()->status)->toBe(DocumentStatus::Failed)
        ->and($document->chunks()->count())->toBe(0);
});
