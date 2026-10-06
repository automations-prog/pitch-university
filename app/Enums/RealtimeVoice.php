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

    /**
     * Whether the voice plainly sounds like its gender. Alloy and sage can
     * pass for either, so a roleplay consumer never gets them.
     */
    public function isClearlyGendered(): bool
    {
        return ! in_array($this, [self::Alloy, self::Sage], true);
    }

    /**
     * The voice used when a consumer has none of their own gender.
     *
     * @param  'male'|'female'  $gender
     */
    public static function defaultFor(string $gender): self
    {
        return $gender === 'male' ? self::Cedar : self::Marin;
    }
}
