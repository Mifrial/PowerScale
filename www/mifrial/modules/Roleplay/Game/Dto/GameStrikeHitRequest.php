<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Запрос броска попадания: цель удара, проверка и накопленные сдвиги.
 */
final class GameStrikeHitRequest
{
    /**
     * Создаёт запрос броска.
     *
     * @param GameStrikeTarget $target Цель удара.
     * @param string $ruleCode Код проверки попадания.
     * @param string $actionRuleCode Карточка действия.
     * @param array<int, RollAdvantage> $advantages Преимущества проверки.
     * @param int $pendingAccuracyDelta Точность из хвоста прошлого действия.
     * @param RollAdjustment|null $pendingAdjustment Успехи из хвоста прошлого действия.
     *
     * @return void
     */
    public function __construct(
        public readonly GameStrikeTarget $target,
        public readonly string $ruleCode,
        public readonly string $actionRuleCode,
        public readonly array $advantages,
        public readonly int $pendingAccuracyDelta,
        public readonly ?RollAdjustment $pendingAdjustment,
    ) {
    }
}
