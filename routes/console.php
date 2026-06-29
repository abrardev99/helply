<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

// Kick off ingestion crawls for bots with pending documents. withoutOverlapping() stops
// a slow run from being launched twice; the command itself claims rows (pending ->
// processing) so it never re-dispatches a crawl that is already queued or running.
Schedule::command('documents:queue-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();
