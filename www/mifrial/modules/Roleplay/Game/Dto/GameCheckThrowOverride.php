<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Смена пула проверки: характеристика, число кубов или преимущество.
 */
final class GameCheckThrowOverride
{
    /**
     * Собирает смену.
     *
     * @param string|null $characteristicCode Код характеристики или null.
     * @param int|null $diceCount Число кубов свободного пула или null.
     * @param int|null $advantage Дельта преимущества или null.
     *
     * @return void
     */
    public function __construct(
        public readonly ?string $characteristicCode = null,
        public readonly ?int $diceCount = null,
        public readonly ?int $advantage = null,
    ) {
    }
}
