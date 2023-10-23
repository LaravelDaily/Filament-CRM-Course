<?php

namespace App\Policies;

use App\Models\User;

class CustomFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
