<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Изменяемое состояние одного закрытия одиночного удара.
 */
final class GameStrikeSingleRun
{
    public array $open = [];

    public GameBattleRecord $battle;

    public int $strikeId = 0;

    public array $strikes = [];

    public string $actionRuleCode = '';

    public ?PushSpec $pushSpec = null;

    public bool $isPush = false;

    public bool $sequential = false;

    public int $activeIndex = 0;

    public array $row = [];

    public ?array $resumeState = null;

    public ?array $maneuverResume = null;

    public bool $maneuverDone = false;

    public int $packets = 1;

    public array $target = [];

    public string $targetKey = '';

    public string $attackerKind = '';

    public int $attackerId = 0;

    public ?array $process = null;

    public array $choice = [];

    public array $maneuver = [];

    public bool $maneuverFail = false;

    public array $maneuverMarks = [];

    public bool $turn = false;

    public array $conditions = [];

    public array $targetOpen = [];

    public CharacterRuleSlice $slice;

    public string $profileType = '';

    public int $profileIndex = 0;

    public int $itemInventoryId = 0;

    public string $itemRuleCode = '';

    public array $attackerChoices = [];

    public array $attackContext = [];

    public int $modifier = 0;

    public array $metrics = [];

    public string $hitCode = '';

    public array $pending = [];

    public array $defenderPending = [];

    public ?string $reactionRuleCode = null;

    public ?string $turnRuleCode = null;

    public array $defenderChoices = [];

    public array $spends = [];

    public ?array $defenderToken = null;

    public int $previousSr = 0;

    public array $supports = [];

    public array $advantages = [];

    public ?RollAdvantage $flank = null;

    public array $strikePackage = [];

    public ?array $defenderRoll = null;

    public array $markKeys = [];

    public array $pushChecks = [];

    public array $attackerRoll = [];

    public bool $defenseRolled = false;

    public array $coverers = [];

    public ?array $coverInfo = null;

    public ?string $damageTypeCode = null;

    public bool $autoFail = false;

    public ?int $pushRating = null;

    public bool $pushCanceled = false;

    public int $success = 0;

    public ?int $srCap = null;

    public int $damageSr = 0;

    public ?DimensionalNumber $damage = null;

    public array $result = [];

    public int $durabilityShave = 0;

    public ?DimensionalNumber $penetration = null;

    public ?DimensionalNumber $resistance = null;

    public ?DimensionalNumber $soak = null;

    public array $packetDamages = [];

    public array $packetInjuries = [];

    public ?DimensionalNumber $injury = null;

    public ?array $prepared = null;

    public ?array $marksAfter = null;

    /**
     * Создаёт состояние прогона.
     *
     * @param GameStrikeSingleInput $input Запрос.
     * @param GameStrikeWriteScope $scope Права записи.
     * @param bool $editAll Ключ.
     *
     * @return void
     */
    public function __construct(
        public readonly GameStrikeSingleInput $input,
        public readonly GameStrikeWriteScope $scope,
        public readonly bool $editAll,
    ) {
    }
}
