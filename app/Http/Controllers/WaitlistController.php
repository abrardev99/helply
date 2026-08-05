<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaitlistSignupRequest;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;

class WaitlistController extends Controller
{
    public function store(WaitlistSignupRequest $request): RedirectResponse
    {
        WaitlistEntry::create($request->validated());

        return back();
    }
}
