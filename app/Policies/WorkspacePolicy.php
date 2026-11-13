<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->roleFor($user, $workspace) !== null;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role !== null && $role->canManageAccounts();
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role !== null && $role->canManageMembers();
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
