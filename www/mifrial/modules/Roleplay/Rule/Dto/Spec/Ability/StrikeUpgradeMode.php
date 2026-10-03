<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Режим улучшения удара.
 */
final class StrikeUpgradeMode
{
    /**
     * Создаёт режим.
     *
     * @param string $code Код.
     * @param string $label Подпись.
     * @param int $injuryCheckAdvantage Помеха проверки увечья.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly int $injuryCheckAdvantage,
    ) {
    }

    /**
     * Код.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Подпись.
     *
     * @return string Текст.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Помеха увечья.
     *
     * @return int Число.
     */
    public function getInjuryCheckAdvantage(): int
    {
        return $this->injuryCheckAdvantage;
    }
}
