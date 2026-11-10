<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Взрыв при дубле граней отклонения.
 */
final class SpellDeviationBurst
{
    /**
     * Создаёт описание взрыва.
     *
     * @param string $damageTypeCode Тип урона.
     * @param int $successRating Рейтинг урона.
     *
     * @return void
     */
    public function __construct(
        private readonly string $damageTypeCode,
        private readonly int $successRating,
    ) {
    }

    /**
     * Тип урона.
     *
     * @return string Код.
     */
    public function getDamageTypeCode(): string
    {
        return $this->damageTypeCode;
    }

    /**
     * Рейтинг урона.
     *
     * @return int Число.
     */
    public function getSuccessRating(): int
    {
        return $this->successRating;
    }
}
