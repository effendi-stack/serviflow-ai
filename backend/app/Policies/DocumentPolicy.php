<?php

namespace App\Policies;

use App\Models\User;

class DocumentPolicy
{
    public function upload(User $user): bool
    {
        return $user->isAdmin();
    }

    public function ask(User $user): bool
    {
        return $user->isAdmin() || $user->isTechnician();
    }
}
