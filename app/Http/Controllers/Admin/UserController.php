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

        return redirect()->route('admin.users.index')->with('success', 'Compte administrateur créé.');
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
            return back()->with('error', 'Impossible de rétrograder le dernier super administrateur actif.');
        }

        $user->syncRoles([$newRole]);

        return redirect()->route('admin.users.index')->with('success', 'Rôle administrateur mis à jour.');
    }

    public function resendResetLink(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Lien de réinitialisation du mot de passe envoyé.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->is_active && $user->isLastActiveSuperAdmin()) {
            return back()->with('error', 'Impossible de désactiver le dernier super administrateur actif.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Statut du compte mis à jour.');
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
            return back()->with('error', 'Impossible de supprimer le dernier super administrateur actif.');
        }

        $hasActedOnAnything = Activity::query()
            ->where('causer_type', User::class)
            ->where('causer_id', $user->id)
            ->exists();

        if ($hasActedOnAnything) {
            return back()->with('error', 'Ce compte a une activité enregistrée — désactivez-le plutôt que de le supprimer.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Compte administrateur supprimé.');
    }
}
