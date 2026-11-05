<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Прикрытие союзника: помехи защите и перенос фактической цели.
 */
final class CoverAlly
{
    /**
     * Побеждает защита с наибольшим рейтингом.
     */
    public const SUCCESS_HIGHEST_RATING = 'highest_rating';

    /**
     * При полном провале цель выбирает атакующий.
     */
    public const FAILURE_ATTACKER_CHOICE = 'attacker_choice';

    /**
     * Фактической целью становится защищавшийся.
     */
    public const BECOMES_ACTUAL_TARGET = 'actual_target';

    /**
     * Создаёт прикрытие.
     *
     * @param array<int, DefenseAdvantage> $defenseAdvantages Помехи защите.
     * @param array<int, string> $reactions Разрешённые защиты.
     * @param array{success: string, failure: string, becomes: string} $resolution Стратегии.
     * @param string|null $distance Как считается дистанция.
     *
     * @return void
     */
    public function __construct(
        private readonly array $defenseAdvantages,
        private readonly array $reactions,
        private readonly array $resolution,
        private readonly ?string $distance = null,
    ) {
    }

    /**
     * Помехи защите прикрывающего.
     *
     * @return array<int, DefenseAdvantage> Список.
     */
    public function getDefenseAdvantages(): array
    {
        return $this->defenseAdvantages;
    }

    /**
     * Разрешённые защиты.
     *
     * @return array<int, string> Коды реакций.
     */
    public function getReactions(): array
    {
        return $this->reactions;
    }

    /**
     * Стратегии переноса цели.
     *
     * @return array{success: string, failure: string, becomes: string} Стратегии.
     */
    public function getResolution(): array
    {
        return $this->resolution;
    }

    /**
     * Как считается дистанция.
     *
     * @return string|null Значение или null.
     */
    public function getDistance(): ?string
    {
        return $this->distance;
    }
}
