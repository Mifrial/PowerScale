<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Definition с обязательным аргументом конструктора.
 */
final class CtorArgProbeTable extends SmartTableDefinition
{
    /**
     * Создаёт definition с обязательным аргументом.
     *
     * @param string $tableSuffix Суффикс, не используется таблицей.
     *
     * @return void
     */
    public function __construct(string $tableSuffix)
    {
        unset($tableSuffix);
    }

    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_ctor';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [new IdField()];
    }
}
