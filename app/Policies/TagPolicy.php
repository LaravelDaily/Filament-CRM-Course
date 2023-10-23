<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
