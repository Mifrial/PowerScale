<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Вход расчёта прикрытия строки удара.
 */
final class GameStrikeCoverRun
{
    public GameRecord $game;

    /** @var array<string, mixed> */
    public array $open;

    /** @var array<string, mixed> */
    public array $conditions;

    /** @var array<string, mixed> */
    public array $choice;

    /** @var array<string, mixed> */
    public array $attackerRoll;

    public string $hitCode;

    public CharacterRuleSlice $slice;

    /** @var array<string, mixed> */
    public array $pending;

    /** @var array<string, mixed> */
    public array $targetPending;

    public string $profileType;

    public bool $isPush;

    public ?DimensionalNumber $shotDistance = null;

    public int $targetSheetVersion = 0;

    /** @var array<int, array<string, mixed>> */
    public array $marks = [];
}
