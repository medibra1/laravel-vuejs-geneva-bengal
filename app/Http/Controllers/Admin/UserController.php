<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->role(['admin', 'super_admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'last_login_at'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form');
    }

    /**
     * No password is set here — a random, unusable one is generated and
     * the native Laravel reset-link flow takes over, so the new admin
     * chooses their own password rather than one being emailed in clear
     * text. See CLAUDE.md: "Gestion des comptes admin".
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Str::random(40),
            'is_active' => true,
        ]);

        $user->assignRole($request->validated('role'));

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.index')->with('success', __('Admin account created.'));
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $newRole = $request->validated('role');

        if ($newRole !== 'super_admin' && $user->isLastActiveSuperAdmin()) {
            return back()->with('error', __('You cannot demote the last active super_admin.'));
        }

        $user->syncRoles([$newRole]);

        return redirect()->route('admin.users.index')->with('success', __('Admin role updated.'));
    }

    public function resendResetLink(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', __('Password reset link sent.'));
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->is_active && $user->isLastActiveSuperAdmin()) {
            return back()->with('error', __('You cannot deactivate the last active super_admin.'));
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', __('Account status updated.'));
    }

    /**
     * Allowed only if the account never DID anything logged (as the
     * activity log's causer) — not whether it was ever itself the
     * subject of a logged change, which would always be true (account
     * creation is logged) and make this guard unreachable. An admin who
     * created their account but never acted can be hard-deleted; one who
     * has an audit trail behind them gets deactivated instead, so that
     * trail (and any FKs pointing at them) stays intact.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->isLastActiveSuperAdmin()) {
            return back()->with('error', __('You cannot delete the last active super_admin.'));
        }

        $hasActedOnAnything = Activity::query()
            ->where('causer_type', User::class)
            ->where('causer_id', $user->id)
            ->exists();

        if ($hasActedOnAnything) {
            return back()->with('error', __('This account has logged activity — deactivate it instead of deleting it.'));
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', __('Admin account deleted.'));
    }
}
