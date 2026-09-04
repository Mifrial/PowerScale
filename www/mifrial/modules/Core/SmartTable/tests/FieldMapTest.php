<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\HtmlField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Interface\Field\IFieldHydrator;
use Mifrial\Core\SmartTable\Table\RuntimeDefinition;
use Mifrial\Core\SmartTable\Tests\Fixture\AbstractProbeTable;
use Mifrial\Core\SmartTable\Tests\Fixture\BothIndexFlagsTable;
use Mifrial\Core\SmartTable\Tests\Fixture\CascadeMultipleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\JsonMultipleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\LongStringMultipleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\LongUniqueStringTable;
use Mifrial\Core\SmartTable\Tests\Fixture\ParentRefTable;
use Mifrial\Core\SmartTable\Tests\Fixture\SampleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\TableWithoutId;
use Mifrial\Core\SmartTable\Tests\Fixture\TextMultipleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\UniqueIdTable;
use Mifrial\Core\SmartTable\Tests\Fixture\UniqueMultipleTable;
use Mifrial\Core\SmartTable\Tests\Fixture\UniqueTextTable;
use Mifrial\Core\SmartTable\Tests\Fixture\UniqueTitleTable;
use PHPUnit\Framework\TestCase;

final class FieldMapTest extends TestCase
{
    /**
     * Проверяет карту с id и именем таблицы.
     *
     * @return void
     */
    public function testSampleTableMap(): void
    {
        $table = new SampleTable();
        $fieldMap = $table->getMap();

        self::assertSame('st_sample', $table->getName());
        self::assertArrayHasKey('id', $fieldMap);
        self::assertInstanceOf(IdField::class, $fieldMap['id']);
        self::assertSame($fieldMap, $table->getMap());
    }

    /**
     * Проверяет отказ карты без id.
     *
     * @return void
     */
    public function testMapWithoutIdFails(): void
    {
        try {
            (new TableWithoutId())->getMap();
            self::fail('map without id must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Indexed/unique недопустимы на id, multiple, text и длинном string.
     *
     * @return void
     */
    public function testIndexFlagsRejectedOnMap(): void
    {
        $invalidTables = [
            new UniqueTextTable(),
            new UniqueMultipleTable(),
            new UniqueIdTable(),
            new LongUniqueStringTable(),
        ];
        foreach ($invalidTables as $table) {
            try {
                $table->getMap();
                self::fail('invalid index flags must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }

        $valid = new UniqueTitleTable();
        self::assertTrue($valid->getMap()['title']->settings()->unique());
        $both = new BothIndexFlagsTable();
        self::assertTrue($both->getMap()['title']->settings()->indexed());
        self::assertTrue($both->getMap()['title']->settings()->unique());
    }

    /**
     * Проверяет UTF-8 длину string.
     *
     * @return void
     */
    public function testStringUtf8Length(): void
    {
        $field = new StringField('title', FieldSettings::fromOptions(), 255);
        $tooLong = str_repeat('я', 256);

        try {
            $field->cast($tooLong, true);
            self::fail('cyrillic overflow must fail');
        } catch (FieldInvalidException $exception) {
            self::assertSame('FIELD_INVALID', $exception->getErrorCode());
        }

        self::assertSame('я', $field->extract($field->cast('я', true)));
    }

    /**
     * Проверяет int и datetime round-trip.
     *
     * @return void
     */
    public function testIntAndDateTime(): void
    {
        $intField = new IntField('age', FieldSettings::fromOptions(), 0, 120);
        self::assertSame(10, $intField->hydrate($intField->extract($intField->cast(10, true))));

        try {
            $intField->cast(1.5, true);
            self::fail('float must fail int cast');
        } catch (FieldInvalidException $exception) {
            self::assertSame('FIELD_INVALID', $exception->getErrorCode());
        }

        $dateField = new DateTimeField('created', FieldSettings::fromOptions());
        $moment = UnixDateTime::fromUnix(1700000000);
        self::assertSame(1700000000, $dateField->extract($dateField->cast($moment, true)));
        self::assertSame(1700000000, $dateField->hydrate('1700000000')->toUnix());

        try {
            $dateField->cast(1700000000, true);
            self::fail('int must fail datetime cast');
        } catch (FieldInvalidException $exception) {
            self::assertSame('FIELD_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет bool, json, text, html, id null и multiple.
     *
     * @return void
     */
    public function testBoolJsonIdMultiple(): void
    {
        $boolField = new BoolField('active', FieldSettings::fromOptions());
        self::assertSame(1, $boolField->extract($boolField->cast(true, true)));
        self::assertFalse($boolField->hydrate('0'));

        $jsonField = new JsonField('payload', FieldSettings::fromOptions());
        $document = ['a' => 1];
        self::assertSame($document, $jsonField->extract($jsonField->cast($document, true)));
        self::assertSame($document, $jsonField->hydrate('{"a":1}'));
        self::assertSame($document, $jsonField->hydrate((object) ['a' => 1]));
        self::assertSame('{"a":1}', $jsonField->cast('{"a":1}', true));

        $idField = new IdField();
        self::assertNull($idField->extract($idField->cast(null, true)));
        self::assertSame(3, $idField->hydrate('3'));

        $textField = new TextField('body', FieldSettings::fromOptions());
        self::assertSame('x', $textField->cast('x', true));
        $htmlField = new HtmlField('html', FieldSettings::fromOptions());
        self::assertSame('<b>', $htmlField->cast('<b>', true));

        $multiple = new StringField('tags', FieldSettings::fromOptions(['multiple' => true]));
        self::assertSame(['a', 'b'], $multiple->extract($multiple->cast(['a', 'b'], true)));
        try {
            $multiple->cast('a', true);
            self::fail('scalar multiple must fail');
        } catch (FieldInvalidException $exception) {
            self::assertSame('FIELD_INVALID', $exception->getErrorCode());
        }

        try {
            $multiple->extract($multiple->cast(['a', 'a'], true));
            self::fail('duplicate multiple must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Multiple text/json и длинный string на карте.
     *
     * @return void
     */
    public function testMapRejectsUnsupportedMultiple(): void
    {
        try {
            (new TextMultipleTable())->getMap();
            self::fail('text multiple must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            (new LongStringMultipleTable())->getMap();
            self::fail('long string multiple must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            (new JsonMultipleTable())->getMap();
            self::fail('json multiple must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет required null.
     *
     * @return void
     */
    public function testRequiredNullFails(): void
    {
        $field = new StringField('title', FieldSettings::fromOptions(['required' => true]));
        try {
            $field->cast(null, true);
            self::fail('required null must fail');
        } catch (FieldRequiredException $exception) {
            self::assertSame('FIELD_REQUIRED', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет ключ подписи и fallback на имя поля.
     *
     * @return void
     */
    public function testLabelFallsBackToFieldName(): void
    {
        $unnamed = new StringField('title', FieldSettings::fromOptions());
        self::assertSame('title', $unnamed->label());

        $named = new StringField('title', FieldSettings::fromOptions(['label' => 'FIELD_TITLE']));
        self::assertSame('FIELD_TITLE', $named->label());
    }

    /**
     * Проверяет TINYINT с длиной 1.
     *
     * @return void
     */
    public function testBoolColumnLength(): void
    {
        $column = (new BoolField('active', FieldSettings::fromOptions()))->column();
        self::assertSame('TINYINT', $column->sqlType);
        self::assertSame(1, $column->length);
    }

    /**
     * Проверяет type и отказы конструктора reference.
     *
     * @return void
     */
    public function testReferenceFieldContract(): void
    {
        $field = new ReferenceField('parent_id', FieldSettings::fromOptions(), ParentRefTable::class);
        self::assertSame('reference', $field->type());
        self::assertSame('INT', $field->column()->sqlType);
        self::assertSame('st_ref_parent', $field->targetTableName());
        self::assertSame('id', $field->targetIdField());
        self::assertSame(ParentRefTable::class, $field->targetDefinitionClass());
        self::assertSame(3, $field->cast(3, true));
        try {
            $field->cast(0, true);
            self::fail('zero reference must fail');
        } catch (FieldInvalidException $exception) {
            self::assertSame('FIELD_INVALID', $exception->getErrorCode());
        }

        try {
            $field->cast(-1, true);
            self::fail('negative reference must fail');
        } catch (FieldInvalidException $exception) {
            self::assertSame('FIELD_INVALID', $exception->getErrorCode());
        }

        $invalidCases = [
            [FieldSettings::fromOptions(['required' => true]), ParentRefTable::class, 'setNull'],
            [FieldSettings::fromOptions(['required' => true]), ParentRefTable::class, 'none'],
            [FieldSettings::fromOptions(['multiple' => true]), ParentRefTable::class, 'restrict'],
            [FieldSettings::fromOptions(), \stdClass::class, 'restrict'],
            [FieldSettings::fromOptions(), AbstractProbeTable::class, 'restrict'],
            [FieldSettings::fromOptions(), '', 'restrict'],
            [FieldSettings::fromOptions(), 'NopeNotATable', 'restrict'],
        ];
        foreach ($invalidCases as [$settings, $targetClass, $onDelete]) {
            try {
                new ReferenceField('parent_id', $settings, $targetClass, $onDelete);
                self::fail('invalid reference ctor must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }

    /**
     * bigint, широкий id, required cascade; cascade+multiple на карте — отказ.
     *
     * @return void
     */
    public function testBigintAndCascadeMap(): void
    {
        $bigInt = new IntField('score', FieldSettings::fromOptions(), 0, null, true);
        self::assertTrue($bigInt->isBig());
        self::assertSame('bigint', $bigInt->type());
        self::assertSame('BIGINT', $bigInt->column()->sqlType);
        self::assertSame(3, $bigInt->hydrate('3'));

        $wideId = IdField::big();
        self::assertTrue($wideId->isBig());
        self::assertSame('bigint', $wideId->type());
        self::assertSame('BIGINT', $wideId->column()->sqlType);

        $cascade = new ReferenceField(
            'parent_id',
            FieldSettings::fromOptions(['required' => true]),
            ParentRefTable::class,
            'cascade',
        );
        self::assertSame('cascade', $cascade->onDelete());
        self::assertSame('INT', $cascade->column()->sqlType);

        try {
            (new CascadeMultipleTable())->getMap();
            self::fail('cascade with multiple must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет JSON-совместимость hydrator на cast.
     *
     * @return void
     */
    public function testJsonHydratorCastRejectsIncompatibleExtract(): void
    {
        $hydrator = new class () implements IFieldHydrator {
            public function hydrate(mixed $decodedValue): mixed
            {
                return $decodedValue;
            }

            public function extract(mixed $domainValue): mixed
            {
                return NAN;
            }
        };
        $field = new JsonField('payload', FieldSettings::fromOptions(), $hydrator);

        try {
            $field->cast('payload', true);
            self::fail('non-json hydrator extract must fail on cast');
        } catch (FieldInvalidException $exception) {
            self::assertSame('FIELD_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Составной unique: валидный кортеж и отказы карты.
     *
     * @return void
     */
    public function testCompositeUniqueKeysOnMap(): void
    {
        $pairFields = [
            new StringField('left_val', FieldSettings::fromOptions()),
            new StringField('right_val', FieldSettings::fromOptions()),
        ];
        $valid = new RuntimeDefinition('st_uk_ok', $pairFields, [['left_val', 'right_val']]);
        self::assertSame([['left_val', 'right_val']], $valid->getUniqueKeys());

        foreach ($this->invalidUniqueDefinitions($pairFields) as $definition) {
            try {
                $definition->getMap();
                self::fail('invalid unique keys must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }

    /**
     * Карты с недопустимым составным unique.
     *
     * @param array<int, StringField> $pairFields Два string-поля.
     *
     * @return array<int, RuntimeDefinition> Определения.
     */
    private function invalidUniqueDefinitions(array $pairFields): array
    {
        return [
            new RuntimeDefinition('st_uk_one', $pairFields, [['left_val']]),
            new RuntimeDefinition('st_uk_unk', $pairFields, [['left_val', 'missing']]),
            new RuntimeDefinition('st_uk_dupf', $pairFields, [['left_val', 'left_val']]),
            new RuntimeDefinition('st_uk_dupt', $pairFields, [
                ['left_val', 'right_val'],
                ['left_val', 'right_val'],
            ]),
            new RuntimeDefinition('st_uk_txt', [
                new TextField('body', FieldSettings::fromOptions()),
                new StringField('title', FieldSettings::fromOptions()),
            ], [['body', 'title']]),
            new RuntimeDefinition('st_uk_clash', [
                new StringField('a', FieldSettings::fromOptions()),
                new StringField('b', FieldSettings::fromOptions()),
                new StringField('a_b', FieldSettings::fromOptions(['unique' => true])),
            ], [['a', 'b']]),
        ];
    }
}
