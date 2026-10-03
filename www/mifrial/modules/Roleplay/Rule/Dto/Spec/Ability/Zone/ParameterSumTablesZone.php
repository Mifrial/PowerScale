<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;

/**
 * Цена суммой таблиц нескольких параметров.
 */
final class ParameterSumTablesZone implements AbilityZone
{
    /**
     * Создаёт цену.
     *
     * @param int $maxLevel Потолок.
     * @param array<string, array<string, int>> $tables Параметр → таблица.
     *
     * @return void
     */
    public function __construct(
        private readonly int $maxLevel,
        private readonly array $tables,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'parameter_sum_tables';
    }

    /**
     * Потолок.
     *
     * @return int Уровень.
     */
    public function getMaxLevel(): int
    {
        return $this->maxLevel;
    }

    /**
     * Таблицы.
     *
     * @return array<string, array<string, int>> Словарь.
     */
    public function getTables(): array
    {
        return $this->tables;
    }
}
