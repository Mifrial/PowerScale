<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Запрос броска — Record: параметры конструктора это поля, не порты.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Запрос броска блока.
 */
final class GameStrikeBlockRequest
{
    /**
     * Создаёт запрос блока.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код проверки попадания.
     * @param array<string, mixed> $sheet Лист защитника.
     * @param array<string, mixed> $choices Выборы защитника.
     * @param int $blockItemInventoryId Строка предмета блока.
     * @param int $blockItemProfileIndex Профиль блока.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string|null $participantKey Ключ листа защитника.
     * @param array<int, RollAdvantage> $advantages Преимущества проверки.
     *
     * @return void
     */
    public function __construct(
        public readonly int $spaceId,
        public readonly int $rulesRevision,
        public readonly string $ruleCode,
        public readonly array $sheet,
        public readonly array $choices,
        public readonly int $blockItemInventoryId,
        public readonly int $blockItemProfileIndex,
        public readonly array $attackerRoll,
        public readonly ?string $participantKey,
        public readonly array $advantages,
    ) {
    }
}
