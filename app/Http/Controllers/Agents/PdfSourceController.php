<?php

namespace App\Http\Controllers\Agents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\StorePdfRequest;
use App\Jobs\ImportPdfJob;
use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PdfSourceController extends Controller
{
    /**
     * Upload a PDF to a agent and queue its text extraction.
     */
    public function store(StorePdfRequest $request, string $currentTeam, Agent $agent): RedirectResponse
    {
        $file = $request->file('file');

        // Stored on the private "local" disk so the raw PDF is never web-accessible.
        $path = $file->store("pdfs/{$agent->id}", 'local');

        $document = $agent->documents()->create([
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
