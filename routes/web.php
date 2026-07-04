<?php

use App\Http\Controllers\Bots\BotController;
use App\Http\Controllers\Bots\ConversationsController;
use App\Http\Controllers\Bots\DocumentController;
use App\Http\Controllers\Bots\PdfSourceController;
use App\Http\Controllers\Bots\RecrawlBotController;
use App\Http\Controllers\Bots\ReembedController;
use App\Http\Controllers\Bots\WebsiteSourceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\WidgetScriptController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('widget.js', WidgetScriptController::class)->name('widget.script');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::resource('bots', BotController::class);

        Route::post('bots/{bot}/sources', [WebsiteSourceController::class, 'store'])->name('bots.sources.store');
        Route::post('bots/{bot}/pdfs', [PdfSourceController::class, 'store'])->name('bots.pdfs.store');
        Route::post('bots/{bot}/recrawl', RecrawlBotController::class)->name('bots.recrawl');
        Route::post('bots/{bot}/reembed', ReembedController::class)->name('bots.reembed');
        Route::delete('bots/{bot}/documents/{document}', [DocumentController::class, 'destroy'])->name('bots.documents.destroy');

        Route::get('bots/{bot}/conversations', [ConversationsController::class, 'index'])->name('bots.conversations.index');
        Route::get('bots/{bot}/conversations/{conversation}', [ConversationsController::class, 'show'])->name('bots.conversations.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
