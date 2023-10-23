<?php

namespace App\Policies;

use App\Models\User;

class LeadSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
