<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Publishing = 'publishing';
    case Partial = 'partial';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
