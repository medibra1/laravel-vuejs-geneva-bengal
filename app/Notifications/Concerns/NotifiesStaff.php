<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared by every entry point that notifies admin/super_admin staff of a
 * new event (contact requests, deposits) — see CLAUDE.md.
 */
trait NotifiesStaff
{
    /**
     * $excludeUserId drops a staff member from their own notification —
     * e.g. an admin who just created a reservation for a walk-in client
     * from the admin wizard shouldn't be told about their own action.
     *
     * @return Collection<int, User>
     */
    private function activeStaff(?int $excludeUserId = null): Collection
    {
        return User::role(['admin', 'super_admin'])
            ->where('is_active', true)
            ->when($excludeUserId !== null, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->get();
    }
}
