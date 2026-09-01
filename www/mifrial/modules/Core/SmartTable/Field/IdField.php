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
     *
     * @return void
     *
     * @throws MapInvalidException Если имя не id.
     */
    public function __construct(?FieldSettings $fieldSettings = null)
    {
        $settings = $fieldSettings ?? FieldSettings::fromOptions(['required' => true]);
        parent::__construct('id', $settings, 1, null);
        if ($settings->multiple()) {
            throw new MapInvalidException('id cannot be multiple');
        }
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
