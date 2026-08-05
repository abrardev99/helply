<?php

use App\Http\Controllers\Agents\AgentController;
use App\Http\Controllers\Agents\ConversationsController;
use App\Http\Controllers\Agents\DocumentController;
use App\Http\Controllers\Agents\PdfSourceController;
use App\Http\Controllers\Agents\RecrawlAgentController;
use App\Http\Controllers\Agents\ReembedController;
use App\Http\Controllers\Agents\WebsiteSourceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\WidgetScriptController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The landing page is static marketing copy, so it is served without the session,
// CSRF and Inertia-sharing middleware the rest of the app relies on. Those are what
// reach for the database (sessions table, Pennant's features table, the authenticated
// user), and dropping them lets `/` render even when no database is reachable.
Route::inertia('/', 'welcome')
    ->name('home')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        HandleInertiaRequests::class,
        SetTeamUrlDefaults::class,
    ]);

Route::get('widget.js', WidgetScriptController::class)->name('widget.script');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::resource('agents', AgentController::class);

        Route::post('agents/{agent}/sources', [WebsiteSourceController::class, 'store'])->name('agents.sources.store');
        Route::post('agents/{agent}/pdfs', [PdfSourceController::class, 'store'])->name('agents.pdfs.store');
        Route::post('agents/{agent}/recrawl', RecrawlAgentController::class)->name('agents.recrawl');
        Route::post('agents/{agent}/reembed', ReembedController::class)->name('agents.reembed');
        Route::delete('agents/{agent}/documents/{document}', [DocumentController::class, 'destroy'])->name('agents.documents.destroy');

        Route::get('agents/{agent}/conversations', [ConversationsController::class, 'index'])->name('agents.conversations.index');
        Route::get('agents/{agent}/conversations/{conversation}', [ConversationsController::class, 'show'])->name('agents.conversations.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
