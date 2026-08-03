<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\NewContactRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function store(StoreContactRequestRequest $request): RedirectResponse
    {
        $contactRequest = ContactRequest::create($request->validated());

        $staff = User::role(['admin', 'super_admin'])->where('is_active', true)->get();

        Notification::send($staff, new NewContactRequestNotification($contactRequest));

        return back()->with('success', __('Your message has been sent.'));
    }
}
