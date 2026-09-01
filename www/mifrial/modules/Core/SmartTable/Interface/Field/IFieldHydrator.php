<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Field;

/**
 * Преобразование JSON-документа в объект модуля и обратно.
 */
interface IFieldHydrator
{
    /**
     * Собирает объект из JSON-совместимого значения.
     *
     * @param mixed $decodedValue Array или скаляр из JSON.
     *
     * @return mixed Объект или нормализованное значение.
     */
    public function hydrate(mixed $decodedValue): mixed;

    /**
     * Раскладывает объект в JSON-совместимое значение.
     *
     * @param mixed $domainValue Объект поля.
     *
     * @return mixed Array или скаляр.
     */
    public function extract(mixed $domainValue): mixed;
}
