<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Catalog;

use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Подмена default datetime в спеке словаря до FieldSettings.
 */
final class DatetimeSpecDefault
{
    /**
     * Нормализует ключ default для типа поля.
     *
     * @param array<string, mixed> $fieldSpec Спека.
     * @param string $fieldType Тип.
     *
     * @return array<string, mixed> Спека.
     *
     * @throws MapInvalidException Если default недопустим.
     */
    public function apply(array $fieldSpec, string $fieldType): array
    {
        if (!array_key_exists('default', $fieldSpec)) {
            return $fieldSpec;
        }

        if ($fieldSpec['default'] instanceof DateTimeNow && $fieldType !== 'datetime') {
            throw new MapInvalidException('DateTime now default is not allowed on this field');
        }

        if ($fieldType !== 'datetime') {
            return $fieldSpec;
        }

        $fieldSpec['default'] = $this->datetimeDefault($fieldSpec['default']);

        return $fieldSpec;
    }

    /**
     * Нормализует default datetime из спеки.
     *
     * @param mixed $defaultValue Вход.
     *
     * @return UnixDateTime|DateTimeNow Значение карты.
     *
     * @throws MapInvalidException Если тип default неверен.
     */
    private function datetimeDefault(mixed $defaultValue): UnixDateTime|DateTimeNow
    {
        if ($defaultValue instanceof DateTimeNow || $defaultValue instanceof UnixDateTime) {
            return $defaultValue;
        }

        if ($defaultValue === 'now') {
            return DateTimeNow::instance();
        }

        if (!is_int($defaultValue)) {
            throw new MapInvalidException('Datetime default is invalid');
        }

        return UnixDateTime::fromUnix($defaultValue);
    }
}
