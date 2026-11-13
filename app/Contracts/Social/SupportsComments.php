<?php

namespace App\Contracts\Social;

use App\Models\Comment;

interface SupportsComments
{
    public function replyToComment(Comment $comment, string $text): Comment;

    public function setCommentVisibility(Comment $comment, bool $hidden): void;
}
