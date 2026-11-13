<?php

namespace App\Enums;

enum Provider: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Threads = 'threads';
    case LinkedIn = 'linkedin';
    case YouTube = 'youtube';
    case X = 'x';
    case TikTok = 'tiktok';
    case Pinterest = 'pinterest';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Threads => 'Threads',
            self::LinkedIn => 'LinkedIn',
            self::YouTube => 'YouTube',
            self::X => 'X (Twitter)',
            self::TikTok => 'TikTok',
            self::Pinterest => 'Pinterest',
        };
    }

    public function supportsPkce(): bool
    {
        return match ($this) {
            self::X, self::TikTok, self::YouTube => true,
            default => false,
        };
    }
}
