<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Service\FieldSpecAssembler;
use Mifrial\Core\SmartTable\Table\RuntimeDefinition;
use Mifrial\Core\SmartTable\Tests\Fixture\SampleTable;
use PHPUnit\Framework\TestCase;

final class FieldSpecAssemblerTest extends TestCase
{
    /**
     * Собирает string и отвергает id.
     *
     * @return void
     */
    public function testAssembleStringAndRejectsId(): void
    {
        $fields = (new FieldSpecAssembler())->assembleAll([
            ['name' => 'title', 'type' => 'string', 'required' => true, 'maxLength' => 64],
        ]);
        self::assertCount(1, $fields);
        self::assertInstanceOf(StringField::class, $fields[0]);
        self::assertSame(64, $fields[0]->maxLength());

        try {
            (new FieldSpecAssembler())->assembleOne(['name' => 'id', 'type' => 'string']);
            self::fail('id spec must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * hydrator и неизвестный type — MAP_INVALID.
     *
     * @return void
     */
    public function testRejectsHydratorAndUnknownType(): void
    {
        $assembler = new FieldSpecAssembler();
        try {
            $assembler->assembleOne(['name' => 'payload', 'type' => 'json', 'hydrator' => 'x']);
            self::fail('hydrator must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $assembler->assembleOne(['name' => 'payload', 'type' => 'uuid']);
            self::fail('unknown type must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * json без hydrator; RuntimeDefinition добавляет IdField; forTable без class.
     *
     * @return void
     */
    public function testJsonRuntimeAndForTable(): void
    {
        $jsonField = (new FieldSpecAssembler())->assembleOne(['name' => 'payload', 'type' => 'json']);
        self::assertInstanceOf(JsonField::class, $jsonField);
        $definition = new RuntimeDefinition('st_dict_unit', [$jsonField]);
        $fieldMap = $definition->getMap();
        self::assertInstanceOf(IdField::class, $fieldMap['id']);
        self::assertSame('st_dict_unit', $definition->getName());

        $referenceField = (new FieldSpecAssembler())->assembleOne([
            'name' => 'parent_id',
            'type' => 'reference',
            'target' => 'st_sample',
        ]);
        self::assertInstanceOf(ReferenceField::class, $referenceField);
        self::assertSame('st_sample', $referenceField->targetTableName());
        self::assertSame('restrict', $referenceField->onDelete());
        self::assertSame('st_sample', (new SampleTable())->getName());
    }
}
