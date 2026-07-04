<?php

namespace App\Http\Controllers\Bots;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bots\StorePdfRequest;
use App\Jobs\ImportPdfJob;
use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PdfSourceController extends Controller
{
    /**
     * Upload a PDF to a bot and queue its text extraction.
     */
    public function store(StorePdfRequest $request, string $currentTeam, Bot $bot): RedirectResponse
    {
        $file = $request->file('file');

        // Stored on the private "local" disk so the raw PDF is never web-accessible.
        $path = $file->store("pdfs/{$bot->id}", 'local');

        $document = $bot->documents()->create([
            'type' => DocumentType::Pdf,
            'source_url' => $path,
            'title' => $file->getClientOriginalName(),
            'status' => DocumentStatus::Pending,
        ]);

        ImportPdfJob::dispatch($document->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('PDF uploaded.')]);

        return back();
    }
}
