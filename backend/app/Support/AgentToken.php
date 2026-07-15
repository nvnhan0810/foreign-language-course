<?php

namespace App\Support;

final class AgentToken
{
    public const NAME = 'flc-agent';

    public const ABILITY_LOOKUP = 'agent:lookup';

    public const ABILITY_VOCAB = 'agent:vocab';

    public const ABILITY_CURATE = 'agent:curate';

    /** @return list<string> */
    public static function defaultAbilities(): array
    {
        return [
            self::ABILITY_LOOKUP,
            self::ABILITY_VOCAB,
            self::ABILITY_CURATE,
        ];
    }

    /** @return list<string> */
    public static function allowedAbilities(): array
    {
        return self::defaultAbilities();
    }
}
