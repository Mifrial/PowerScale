<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Field\FileReferenceField;
use Mifrial\Core\File\Table\FileTable;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use PHPUnit\Framework\TestCase;

final class FileReferenceFieldTest extends TestCase
{
    /**
     * Ссылка на файл — reference на `file`.
     *
     * @return void
     */
    public function testTargetsFileTable(): void
    {
        $field = new FileReferenceField('cover_id', FieldSettings::fromOptions(['required' => true]));
        self::assertSame('reference', $field->type());
        self::assertSame('file', $field->targetTableName());
        self::assertSame(FileTable::class, $field->targetDefinitionClass());
        self::assertSame('restrict', $field->onDelete());
    }

    /**
     * Правила базового reference не обходятся.
     *
     * @return void
     */
    public function testKeepsReferenceRules(): void
    {
        try {
            new FileReferenceField('cover_id', FieldSettings::fromOptions(['required' => true]), 'setNull');
            self::fail('required file reference must not set null');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }
}
