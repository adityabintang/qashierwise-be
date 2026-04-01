<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Scheduled => 'Scheduled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Scheduled => 'warning',
        };
    }
}
