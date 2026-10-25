<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Смягчение одного удара: характеристика реакции и польза профиля.
 */
final class GameStrikeSoaks
{
    /**
     * Сдвигает закупку названной характеристики и возвращает пару.
     *
     * @param FormulaContext $context Контекст характеристик.
     * @param string $characteristicCode Код характеристики реакции.
     * @param int $benefit Польза профиля.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если значения характеристики нет.
     */
    public function shift(
        FormulaContext $context,
        string $characteristicCode,
        int $benefit,
    ): DimensionalNumber {
        $value = $context->findCharacteristic($characteristicCode);
        if ($value === null) {
            throw new GameInvalidException('Game strike dodge soak is invalid');
        }

        return $value->modify($benefit);
    }
}
