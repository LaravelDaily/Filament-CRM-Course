<?php

namespace App\Policies;

use App\Models\User;

class PipelineStagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
