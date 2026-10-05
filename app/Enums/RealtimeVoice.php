<?php

namespace App\Enums;

enum RealtimeVoice: string
{
    case Alloy = 'alloy';
    case Ash = 'ash';
    case Ballad = 'ballad';
    case Coral = 'coral';
    case Echo = 'echo';
    case Sage = 'sage';
    case Shimmer = 'shimmer';
    case Verse = 'verse';
    case Marin = 'marin';
    case Cedar = 'cedar';

    /**
     * Whether the voice sounds male or female, so a roleplay consumer's
     * voice matches their name.
     */
    public function gender(): string
    {
        return match ($this) {
            self::Ash, self::Ballad, self::Echo, self::Verse, self::Cedar => 'male',
            self::Alloy, self::Coral, self::Sage, self::Shimmer, self::Marin => 'female',
        };
    }
}
