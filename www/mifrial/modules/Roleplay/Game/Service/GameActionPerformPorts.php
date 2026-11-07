<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Собранные порты сценария действия: параметры конструктора — порты, не поля строки.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Порты сценария не-атакующего действия.
 */
final class GameActionPerformPorts
{
    /**
     * Создаёт порты.
     *
     * @param GameStrikeRules $rules Правила игры.
     * @param GameNpcRepository $npcs NPC игры.
     * @param GameStrikeSheetWrites $sheetWrites Запись листов.
     * @param GameActionCards $cards Карточки действий.
     * @param GamePendingActionCosts $pendingCosts Карта ожидания.
     * @param GameCardAvailability $availability Принадлежность карточки.
     * @param GameStateIncreaseChecks $reactions Проверки прироста состояний.
     *
     * @return void
     */
    public function __construct(
        public readonly GameStrikeRules $rules,
        public readonly GameNpcRepository $npcs,
        public readonly GameStrikeSheetWrites $sheetWrites,
        public readonly GameActionCards $cards,
        public readonly GamePendingActionCosts $pendingCosts,
        public readonly GameCardAvailability $availability,
        public readonly GameStateIncreaseChecks $reactions,
    ) {
    }
}
