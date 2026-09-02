<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Первая карта с дублирующим физ. именем.
 */
final class DupNameAlphaTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_setup_dup';
    }

    /**
     * Перечисляет поля.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [new IdField()];
    }
}
