<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BaseField;

/**
 * Поле с произвольным ColumnMeta для юнита DDL.
 */
final class CustomColumnField extends BaseField
{
    /**
     * Создаёт поле с заданной колонкой.
     *
     * @param string $fieldName Имя.
     * @param ColumnMeta $columnMeta Метаданные.
     *
     * @return void
     */
    public function __construct(
        string $fieldName,
        private readonly ColumnMeta $columnMeta,
    ) {
        parent::__construct($fieldName, FieldSettings::fromOptions());
    }

    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    public function type(): string
    {
        return 'custom';
    }

    /**
     * Возвращает метаданные колонки.
     *
     * @return ColumnMeta Описание SQL-типа.
     */
    public function column(): ColumnMeta
    {
        return $this->columnMeta;
    }

    /**
     * {@inheritdoc}
     */
    protected function castPresent(mixed $inputValue): mixed
    {
        return $inputValue;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractPresent(mixed $phpValue): mixed
    {
        return $phpValue;
    }

    /**
     * {@inheritdoc}
     */
    protected function hydratePresent(mixed $databaseValue): mixed
    {
        return $databaseValue;
    }
}
