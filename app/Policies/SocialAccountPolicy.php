<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class SocialAccountPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->roleFor($user, $workspace) !== null;
    }

    public function view(User $user, SocialAccount $account): bool
    {
        return $this->roleFor($user, $account->workspace) !== null;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role !== null && $role->canManageAccounts();
    }

    public function delete(User $user, SocialAccount $account): bool
    {
        $role = $this->roleFor($user, $account->workspace);

        return $role !== null && $role->canManageAccounts();
    }

    private function roleFor(User $user, Workspace $workspace): ?WorkspaceRole
    {
        if ($workspace->owner_id === $user->id) {
            return WorkspaceRole::Owner;
        }

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        return $member ? WorkspaceRole::tryFrom($member->role) : null;
    }
}
