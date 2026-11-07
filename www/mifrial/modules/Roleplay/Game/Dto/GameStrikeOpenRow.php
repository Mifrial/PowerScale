<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Профиль одной строки открытого удара.
 */
final class GameStrikeOpenRow
{
    public string $profileType = '';

    public int $profileIndex = 0;

    public int $itemInventoryId = 0;

    public string $itemRuleCode = '';

    public string $actionCode = '';

    public string $attackerKind = '';

    public int $attackerId = 0;
}
