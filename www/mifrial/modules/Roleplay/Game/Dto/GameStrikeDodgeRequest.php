<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Запрос броска — Record: параметры конструктора это поля, не порты.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Запрос броска уклона.
 */
final class GameStrikeDodgeRequest
{
    /**
     * Создаёт запрос уклона.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код проверки попадания.
     * @param array<string, mixed> $sheet Лист защитника.
     * @param array<string, mixed> $choices Выборы защитника.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string|null $participantKey Ключ листа защитника.
     * @param array<int, RollAdvantage> $advantages Преимущества проверки.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     * @param string|null $profileType Тип профиля атаки.
     *
     * @return void
     */
    public function __construct(
        public readonly int $spaceId,
        public readonly int $rulesRevision,
        public readonly string $ruleCode,
        public readonly array $sheet,
        public readonly array $choices,
        public readonly array $attackerRoll,
        public readonly ?string $participantKey,
        public readonly array $advantages,
        public readonly ?DimensionalNumber $shotDistance,
        public readonly ?string $profileType,
    ) {
    }
}
