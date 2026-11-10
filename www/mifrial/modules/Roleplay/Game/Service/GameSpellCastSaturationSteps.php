<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор выбранных шагов насыщения из запроса.
 */
final class GameSpellCastSaturationSteps
{
    /**
     * Целое неотрицательное число шагов.
     *
     * @param mixed $value Значение из контекста.
     *
     * @return int Шаги.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: saturationSteps');
        }

        return $value;
    }
}
