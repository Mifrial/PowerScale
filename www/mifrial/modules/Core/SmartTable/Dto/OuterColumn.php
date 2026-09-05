<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Колонка внешней FROM во внутреннем WHERE подзапроса.
 */
final class OuterColumn
{
    /**
     * Создаёт ссылку на поле внешней карты.
     *
     * @param string $fieldName Имя без точки.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя не шаблон без точки.
     */
    public function __construct(
        private readonly string $fieldName,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $fieldName) !== 1) {
            throw new MapInvalidException('Outer column name is invalid');
        }
    }

    /**
     * Возвращает имя поля внешней карты.
     *
     * @return string Имя без точки.
     */
    public function fieldName(): string
    {
        return $this->fieldName;
    }
}
