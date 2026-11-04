<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\LinkSetField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Tests\Fixture\PathOwnerTable;
use PHPUnit\Framework\TestCase;

final class FieldInheritanceTest extends TestCase
{
    /**
     * Наследник reference сохраняет тип и цель, но не обходит правила.
     *
     * @return void
     */
    public function testReferenceSubclassKeepsContract(): void
    {
        $field = new InheritedReferenceField('owner_id', FieldSettings::fromOptions(['required' => true]), 'cascade');
        self::assertSame('reference', $field->type());
        self::assertSame((new PathOwnerTable())->getName(), $field->targetTableName());
        self::assertSame(PathOwnerTable::class, $field->targetDefinitionClass());
        self::assertSame('cascade', $field->onDelete());

        try {
            new InheritedReferenceField('owner_id', FieldSettings::fromOptions(['required' => true]), 'setNull');
            self::fail('required reference must not set null');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            new InheritedReferenceField('owner_id', FieldSettings::fromOptions(['multiple' => true]));
            self::fail('reference must not be multiple');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Наследник linkset сохраняет тип и цель, но не обходит правила.
     *
     * @return void
     */
    public function testLinkSetSubclassKeepsContract(): void
    {
        $field = new InheritedLinkSetField('peers', FieldSettings::fromOptions(['default' => []]), 'none');
        self::assertSame('linkset', $field->type());
        self::assertSame((new PathOwnerTable())->getName(), $field->targetTableName());
        self::assertSame(PathOwnerTable::class, $field->targetDefinitionClass());
        self::assertSame('none', $field->onDelete());

        try {
            new InheritedLinkSetField('peers', FieldSettings::fromOptions(['indexed' => true]));
            self::fail('linkset must not be indexed');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            new InheritedLinkSetField('peers', FieldSettings::fromOptions(), 'cascade');
            self::fail('linkset must not cascade');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }
}

/**
 * Тестовый наследник reference.
 */
final class InheritedReferenceField extends ReferenceField
{
    /**
     * Создаёт поле с целью PathOwnerTable.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param string $onDelete Режим удаления.
     *
     * @return void
     */
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        string $onDelete = 'restrict',
    ) {
        parent::__construct($fieldName, $fieldSettings, PathOwnerTable::class, $onDelete);
    }
}

/**
 * Тестовый наследник linkset.
 */
final class InheritedLinkSetField extends LinkSetField
{
    /**
     * Создаёт поле с целью PathOwnerTable.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param string $onDelete Режим удаления.
     *
     * @return void
     */
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        string $onDelete = 'restrict',
    ) {
        parent::__construct($fieldName, $fieldSettings, PathOwnerTable::class, $onDelete);
    }
}
