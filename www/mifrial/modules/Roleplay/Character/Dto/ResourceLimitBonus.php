<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Бонус или штраф к лимиту ресурса с источником.
 */
final class ResourceLimitBonus
{
    /**
     * Создаёт вклад.
     *
     * @param string|null $sourceRuleCode Код правила-источника.
     * @param int $delta Сдвиг лимита.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $sourceRuleCode,
        private readonly int $delta,
    ) {
    }

    /**
     * Код источника.
     *
     * @return string|null Код или null.
     */
    public function getSourceRuleCode(): ?string
    {
        return $this->sourceRuleCode;
    }

    /**
     * Сдвиг.
     *
     * @return int Дельта.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * JSON строки листа.
     *
     * @return array{sourceRuleCode: string|null, delta: int} Поля.
     */
    public function toNative(): array
    {
        return [
            'sourceRuleCode' => $this->sourceRuleCode,
            'delta' => $this->delta,
        ];
    }
}
