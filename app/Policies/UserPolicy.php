<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    /**
     * Assigning roles is an update on the user record.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('users.assignRoles');
    }

    /*
     * Filament allows an action when the policy has no matching method, so
     * these are stated rather than omitted. Accounts are created with
     * `php artisan make:filament-user`.
     */

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
