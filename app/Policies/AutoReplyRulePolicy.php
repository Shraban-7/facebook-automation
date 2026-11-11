<?php

namespace App\Policies;

use App\Models\AutoReplyRule;
use App\Models\User;

class AutoReplyRulePolicy
{
    public function view(User $user, AutoReplyRule $rule): bool
    {
        return $rule->user_id === $user->id;
    }

    public function update(User $user, AutoReplyRule $rule): bool
    {
        return $rule->user_id === $user->id;
    }

    public function delete(User $user, AutoReplyRule $rule): bool
    {
        return $rule->user_id === $user->id;
    }
}
