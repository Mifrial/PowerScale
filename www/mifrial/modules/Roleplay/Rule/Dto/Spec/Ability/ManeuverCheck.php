<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Пара проверки манёвра: карточка проверки и характеристика броска.
 */
final class ManeuverCheck
{
    /**
     * Создаёт пару.
     *
     * @param string $check Код проверки.
     * @param string $characteristic Код характеристики.
     *
     * @return void
     */
    public function __construct(
        private readonly string $check,
        private readonly string $characteristic,
    ) {
    }

    /**
     * Код проверки.
     *
     * @return string Код.
     */
    public function getCheck(): string
    {
        return $this->check;
    }

    /**
     * Код характеристики.
     *
     * @return string Код.
     */
    public function getCharacteristic(): string
    {
        return $this->characteristic;
    }

    /**
     * JSON-форма для строки удара.
     *
     * @return array{check: string, characteristic: string} Пара.
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'characteristic' => $this->characteristic];
    }
}
