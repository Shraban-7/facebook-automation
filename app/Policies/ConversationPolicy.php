<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->roleFor($user, $conversation->workspace) !== null;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        $role = $this->roleFor($user, $conversation->workspace);

        return $role !== null && $role->canPublish();
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
