<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellValue;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Стабильность поддерживаемого эффекта: `{3|0}.modify(превышение мощи)`.
 *
 * Контроль в стабильность не входит (SR-140). Длительные и обновляемые
 * эффекты фиксируют стабильность после появления; поддерживаемые считают её
 * от текущей используемой мощи.
 */
final class GameSpellStability
{
    private const BASE = 3;

    /**
     * Стабильность эффекта.
     *
     * @param AbilitySpellSpec $card Живая карточка.
     * @param array<string, int|array{base: int, size: int}> $parameters Параметры строки.
     * @param CharacteristicNumber $power Текущая используемая мощь.
     *
     * @return DimensionalNumber Стабильность.
     */
    public function value(AbilitySpellSpec $card, array $parameters, CharacteristicNumber $power): DimensionalNumber
    {
        $required = $this->requiredPower($card->getSpell()->getPower(), $parameters);
        $excess = $required === null ? 0 : $power->characteristicDiffTo($required);

        return (new CharacteristicNumber(self::BASE, 0))->modify(max(0, $excess));
    }

    /**
     * Мощь заклинания: фиксированное число или значение параметра.
     *
     * @param SpellValue $value Значение формы.
     * @param array<string, int|array{base: int, size: int}> $parameters Параметры строки.
     *
     * @return DimensionalNumber|null Мощь или null.
     */
    private function requiredPower(SpellValue $value, array $parameters): ?DimensionalNumber
    {
        $number = $value->getNumber();
        if ($number !== null) {
            return $number;
        }

        $code = $value->getParameterCode();
        if ($code === null) {
            return null;
        }

        $entry = $parameters[$code] ?? null;
        if (is_int($entry)) {
            return new DimensionalNumber($entry, 0);
        }

        if (!is_array($entry)) {
            return null;
        }

        return new DimensionalNumber($entry['base'], $entry['size']);
    }
}
