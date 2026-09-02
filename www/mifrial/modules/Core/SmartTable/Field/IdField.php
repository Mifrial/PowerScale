<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Системный автоинкремент id.
 */
final class IdField extends IntField
{
    /**
     * Создаёт поле id.
     *
     * @param FieldSettings|null $fieldSettings Настройки; multiple запрещён.
     * @param bool $big True — signed BIGINT.
     *
     * @return void
     *
     * @throws MapInvalidException Если multiple.
     */
    public function __construct(?FieldSettings $fieldSettings = null, bool $big = false)
    {
        $settings = $fieldSettings ?? FieldSettings::fromOptions(['required' => true]);
        parent::__construct('id', $settings, 1, null, $big);
        if ($settings->multiple()) {
            throw new MapInvalidException('id cannot be multiple');
        }
    }

    /**
     * Широкий signed BIGINT AUTO_INCREMENT.
     *
     * @param FieldSettings|null $fieldSettings Настройки; multiple запрещён.
     *
     * @return self Поле id.
     *
     * @throws MapInvalidException Если multiple.
     */
    public static function big(?FieldSettings $fieldSettings = null): self
    {
        return new self($fieldSettings, true);
    }

    /**
     * Разрешает null на записи автоинкремента.
     *
     * @return bool true, если null на add допустим.
     */
    protected function allowsNullWrite(): bool
    {
        return true;
    }
}
