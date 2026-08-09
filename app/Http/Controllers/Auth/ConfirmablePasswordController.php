<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view — or, when the request wants JSON
     * (see resources/js/Composables/useConfirmsPassword.ts), just report
     * whether the current session's confirmation is still fresh. Mirrors
     * Laravel's own Illuminate\Auth\Middleware\RequirePassword so the two
     * never disagree on what "still confirmed" means.
     */
    public function show(Request $request): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            $confirmedSecondsAgo = time() - $request->session()->get('auth.password_confirmed_at', 0);

            return response()->json([
                'confirmed' => $confirmedSecondsAgo <= config('auth.password_timeout', 10800),
            ]);
        }

        return Inertia::render('Auth/ConfirmPassword');
    }

    /**
     * Confirm the user's password.
     *
     * Responds with JSON instead of redirecting when asked to — an
     * Inertia visit to this endpoint would otherwise follow the redirect
     * and navigate the whole page away from wherever the sensitive action
     * was triggered (see useConfirmsPassword.ts, which calls this with a
     * plain fetch(), not router.post()).
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        if ($request->wantsJson()) {
            return response()->json(['confirmed' => true]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
