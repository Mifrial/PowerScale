<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Проверенная сохранённая строка ресурса.
 */
final class ResourceRow
{
    /**
     * Создаёт строку ресурса.
     *
     * @param string $ruleCode Live resource rule code.
     * @param ResourceValue $current Authoritative current value.
     * @param int|DimensionalNumber|null $base База лимита (спека / грант).
     * @param array<int, ResourceLimitBonus> $bonuses Вклады с источниками.
     *
     * @return void
     *
     * @throws CharacterInvalidException If the code is empty.
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly ResourceValue $current,
        private readonly int|DimensionalNumber|null $base = null,
        private readonly array $bonuses = [],
    ) {
        if ($ruleCode === '') {
            throw new CharacterInvalidException('Resource rule code is empty');
        }

        foreach ($bonuses as $bonus) {
            if (!$bonus instanceof ResourceLimitBonus) {
                throw new CharacterInvalidException('Resource bonus is invalid');
            }
        }
    }

    /**
     * Возвращает код ресурса.
     *
     * @return string Rule code.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Возвращает типизированное текущее значение.
     *
     * @return ResourceValue Current value.
     */
    public function getCurrent(): ResourceValue
    {
        return $this->current;
    }

    /**
     * База лимита без вкладов.
     *
     * @return int|DimensionalNumber|null База.
     */
    public function getBase(): int|DimensionalNumber|null
    {
        return $this->base;
    }

    /**
     * Вклады к лимиту.
     *
     * @return array<int, ResourceLimitBonus> Бонусы.
     */
    public function getBonuses(): array
    {
        return $this->bonuses;
    }
}
