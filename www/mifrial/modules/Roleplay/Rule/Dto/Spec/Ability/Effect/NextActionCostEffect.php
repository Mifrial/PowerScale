<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Сдвиг цены следующего действия по фильтру признаков или кода правила.
 */
final class NextActionCostEffect implements ActionEffect
{
    /**
     * Создаёт эффект.
     *
     * @param string $resourceCode Ресурс.
     * @param int $delta Сдвиг.
     * @param array<int, string> $keywords Признаки. Пусто, если фильтр — код правила.
     * @param string|null $ruleCode Код правила. Null, если фильтр — признаки.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int $delta,
        private readonly array $keywords,
        private readonly ?string $ruleCode,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'next_action_cost';
    }

    /**
     * Ресурс.
     *
     * @return string Код.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Сдвиг.
     *
     * @return int Значение.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Признаки фильтра.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * Код правила фильтра.
     *
     * @return string|null Код или null.
     */
    public function getRuleCode(): ?string
    {
        return $this->ruleCode;
    }
}
