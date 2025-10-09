<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Enums;

enum UserType: string
{
    case NORMAL = 'normal';
    case VISITOR = 'visitor';
    case BLOCKLIST = 'blocklist';

    public function label(): string
    {
        return match($this) {
            self::NORMAL => 'Normal User',
            self::VISITOR => 'Visitor',
            self::BLOCKLIST => 'Blocklist',
        };
    }
}
