<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;

/**
 * Цена по таблице значений параметра.
 */
final class ParameterTableZone implements AbilityZone
{
    /**
     * Создаёт цену.
     *
     * @param string $parameterCode Параметр.
     * @param array<string, int> $table Значение → цена.
     *
     * @return void
     */
    public function __construct(
        private readonly string $parameterCode,
        private readonly array $table,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'parameter_table';
    }

    /**
     * Параметр.
     *
     * @return string Код.
     */
    public function getParameterCode(): string
    {
        return $this->parameterCode;
    }

    /**
     * Таблица.
     *
     * @return array<string, int> Словарь.
     */
    public function getTable(): array
    {
        return $this->table;
    }
}
