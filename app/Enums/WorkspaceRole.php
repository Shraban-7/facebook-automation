<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageAccounts(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canPublish(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Editor], true);
    }

    public function canApprove(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
