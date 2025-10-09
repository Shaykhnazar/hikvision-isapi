<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Enums;

enum EventType: string
{
    case ACCESS_GRANTED = '0x01';
    case ACCESS_DENIED = '0x02';
    case FACE_RECOGNIZED = '0x4b';
    case CARD_SWIPED = '0x05';

    public function description(): string
    {
        return match($this) {
            self::ACCESS_GRANTED => 'Access Granted',
            self::ACCESS_DENIED => 'Access Denied',
            self::FACE_RECOGNIZED => 'Face Recognition Completed',
            self::CARD_SWIPED => 'Card Swiped',
        };
    }
}
