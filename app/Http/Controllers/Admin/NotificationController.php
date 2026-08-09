<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * $notification is looked up on the current user's own notifications
     * rather than route-model-bound globally — the notifications table has
     * no per-user authorization of its own, so an id alone would let one
     * admin mark another admin's notification as read.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->find($notification);

        abort_unless($notification !== null, 404);

        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
