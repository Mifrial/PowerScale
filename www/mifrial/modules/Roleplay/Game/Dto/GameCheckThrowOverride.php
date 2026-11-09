<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Смена пула проверки: характеристика, число кубов, размер или преимущество.
 */
final class GameCheckThrowOverride
{
    /**
     * Собирает смену.
     *
     * @param string|null $characteristicCode Код характеристики или null.
     * @param int|null $diceCount Число кубов свободного пула или null.
     * @param int|null $advantage Дельта преимущества или null.
     * @param int|null $size Размер успехов свободного пула или null.
     * @param int|null $efficiency Эффективность свободного пула или null.
     * @param string|null $source Источник дельты преимущества или null.
     * @param array<int, RollAdvantage> $advantages Явные преимущества или пусто.
     *
     * @return void
     */
    public function __construct(
        public readonly ?string $characteristicCode = null,
        public readonly ?int $diceCount = null,
        public readonly ?int $advantage = null,
        public readonly ?int $size = null,
        public readonly ?int $efficiency = null,
        public readonly ?string $source = null,
        public readonly array $advantages = [],
    ) {
    }
}
