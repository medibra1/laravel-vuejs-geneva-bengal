<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactRequestRequest;
use App\Models\ContactRequest;
use App\Notifications\Concerns\NotifiesStaff;
use App\Notifications\ContactRequestConfirmedNotification;
use App\Notifications\NewContactRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    use NotifiesStaff;

    public function store(StoreContactRequestRequest $request): RedirectResponse
    {
        $contactRequest = ContactRequest::create($request->validated());

        Notification::send($this->activeStaff(), new NewContactRequestNotification($contactRequest));
        Notification::route('mail', $contactRequest->email)
            ->notify(new ContactRequestConfirmedNotification($contactRequest));

        return back()->with('success', __('Your message has been sent.'));
    }
}
