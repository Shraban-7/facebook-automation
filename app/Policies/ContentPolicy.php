<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Content;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class ContentPolicy
{
    public function view(User $user, Content $content): bool
    {
        return $this->roleFor($user, $content->workspace) !== null;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role !== null && $role->canPublish();
    }

    public function approve(User $user, Content $content): bool
    {
        $role = $this->roleFor($user, $content->workspace);

        return $role !== null && $role->canApprove();
    }

    public function delete(User $user, Content $content): bool
    {
        $role = $this->roleFor($user, $content->workspace);
        if ($role === null) {
            return false;
        }

        return $role->canManageAccounts() || ($role->canPublish() && $content->author_id === $user->id);
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
