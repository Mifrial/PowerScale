<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Снимок вызова — Record: параметры конструктора это поля действия, не порты.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Снимок исполнения модификаторов одного удара.
 */
final class GameActionModifierInput
{
    /**
     * Создаёт снимок.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытая строка удара.
     * @param int $strikeId Удар.
     * @param string $actionRuleCode Карточка действия.
     * @param string $attackerKind Вид участника.
     * @param int $attackerId Атакующий.
     * @param array<int, string> $codes Объявленные коды.
     * @param int $actionCharacteristicModifier Сдвиг характеристики.
     * @param int $gripBonus Бонус удержания.
     * @param int $actorUserId Актор чата.
     *
     * @return void
     */
    public function __construct(
        public readonly GameRecord $game,
        public readonly GameBattleRecord $battle,
        public readonly array $open,
        public readonly int $strikeId,
        public readonly string $actionRuleCode,
        public readonly string $attackerKind,
        public readonly int $attackerId,
        public readonly array $codes,
        public readonly int $actionCharacteristicModifier,
        public readonly int $gripBonus,
        public readonly int $actorUserId,
    ) {
    }
}
