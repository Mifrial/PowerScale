<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Снимок защиты — Record: параметры конструктора это поля, не порты.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Запрос защиты: профиль удара, защитник, реакция и параметры попадания.
 */
final class GameStrikeDefenseTarget
{
    /**
     * Создаёт запрос защиты.
     *
     * @param GameStrikeProfileRef $profile Профиль удара.
     * @param GameStrikeActorRef $defender Защитник.
     * @param string $reaction Реакция.
     * @param int|null $blockItemInventoryId Строка предмета блока или null.
     * @param int $rating Рейтинг попадания.
     * @param DimensionalNumber|null $penetration Пробитие атакующего или null.
     * @param int $durabilityShave Срез надёжности с единиц удара.
     *
     * @return void
     */
    public function __construct(
        public readonly GameStrikeProfileRef $profile,
        public readonly GameStrikeActorRef $defender,
        public readonly string $reaction,
        public readonly ?int $blockItemInventoryId,
        public readonly int $rating,
        public readonly ?DimensionalNumber $penetration,
        public readonly int $durabilityShave,
    ) {
    }
}
