<?php

namespace App\Policies;

use App\Models\FacebookPage;
use App\Models\User;

class FacebookPagePolicy
{
    public function view(User $user, FacebookPage $facebookPage): bool
    {
        return $facebookPage->user_id === $user->id;
    }

    public function update(User $user, FacebookPage $facebookPage): bool
    {
        return $facebookPage->user_id === $user->id;
    }

    public function delete(User $user, FacebookPage $facebookPage): bool
    {
        return $facebookPage->user_id === $user->id;
    }
}
