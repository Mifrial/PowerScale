<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Проверяет выбранную пару {проверка, характеристика}.
 */
final class GameStrikeManeuverCheck
{
    /**
     * Проверяет выбранную пару.
     *
     * @param array<string, mixed> $window Решение.
     * @param mixed $options Разрешённые пары.
     *
     * @return array{check: string, characteristic: string} Пара.
     *
     * @throws GameInvalidException Если пара чужая.
     */
    public function choice(array $window, mixed $options): array
    {
        $check = $window['check'] ?? null;
        $characteristic = $window['characteristic'] ?? null;
        if (!is_string($check) || !is_string($characteristic) || !is_array($options)) {
            throw new GameInvalidException('Game maneuver check is invalid');
        }

        foreach ($options as $option) {
            if ($this->matches($option, $check, $characteristic)) {
                return ['check' => $check, 'characteristic' => $characteristic];
            }
        }

        throw new GameInvalidException('Game maneuver check is invalid');
    }

    /**
     * Совпадает ли разрешённая пара с выбором.
     *
     * @param mixed $option Разрешённая пара.
     * @param string $check Проверка.
     * @param string $characteristic Характеристика.
     *
     * @return bool Признак.
     */
    private function matches(mixed $option, string $check, string $characteristic): bool
    {
        if (!is_array($option)) {
            return false;
        }

        return ($option['check'] ?? null) === $check && ($option['characteristic'] ?? null) === $characteristic;
    }
}
