<?php

use App\Http\Controllers\Api\WidgetChatController;
use App\Http\Middleware\VerifyWidgetOrigin;
use Illuminate\Support\Facades\Route;

// Public, unauthenticated widget endpoint. Origin-checked (VerifyWidgetOrigin) and
// throttled per agent + client IP. The {agent} binding resolves directly by id (see
// AppServiceProvider::configureRouteBindings).
Route::post('widget/{agent}/chat', WidgetChatController::class)
    ->middleware([VerifyWidgetOrigin::class, 'throttle:widget-chat'])
    ->name('widget.chat');
