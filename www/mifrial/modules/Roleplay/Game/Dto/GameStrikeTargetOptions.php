<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Дополнительные данные расчёта одной цели удара.
 */
final class GameStrikeTargetOptions
{
    /**
     * Создаёт данные цели.
     *
     * @param array<int, RollAdvantage> $extraAdvantages Преимущества защиты из поддержек.
     * @param array<string, mixed>|null $prepared Реакция и прикрытие строки.
     * @param array<string, mixed>|null $resolved Готовая защита без переброса.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     *
     * @return void
     */
    public function __construct(
        public readonly array $extraAdvantages = [],
        public readonly ?array $prepared = null,
        public readonly ?array $resolved = null,
        public readonly array $marks = [],
    ) {
    }
}
