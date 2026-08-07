<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class AdminUserService
{
    /**
     * Toggles the block status of a user.
     *
     * @param User $user
     * @return User
     */
    public function toggleBlockStatus(User $user): User
    {
        $newStatus = $user->status === 'active' ? 'blocked' : 'active';
        $user->update(['status' => $newStatus]);

        // If blocked, we could also revoke all active tokens here if using Sanctum
        if ($newStatus === 'blocked') {
            $user->tokens()->delete();
        }

        return $user;
    }



    /**
     * Updates the roles of a user.
     *
     * @param User $user
     * @param array $roles
     * @return User
     */
    public function syncRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);
        return $user;
    }
}
