<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateContactRequestRequest;
use App\Models\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactRequestController extends Controller
{
    public function index(): Response
    {
        $contactRequests = ContactRequest::query()
            ->with('cat:id,name')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ContactRequests/Index', [
            'contactRequests' => $contactRequests,
        ]);
    }

    public function update(UpdateContactRequestRequest $request, ContactRequest $contactRequest): RedirectResponse
    {
        $contactRequest->update($request->validated());

        return redirect()->route('admin.contact-requests.index')->with('success', __('Contact request updated.'));
    }

    public function destroy(ContactRequest $contactRequest): RedirectResponse
    {
        $contactRequest->delete();

        return redirect()->route('admin.contact-requests.index')->with('success', __('Contact request deleted.'));
    }
}
