<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Core\Kernel\Http\JsonDocuments;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Kernel\Value\JsonObject;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Versioning\Space\Dto\VersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет строгую гидратацию mechanics из опубликованной версии правила.
 */
final class RuleVersionRecordTest extends TestCase
{
    /**
     * Отсутствующая или null mechanics означает пустой список.
     *
     * @return void
     */
    public function testEmptyMechanicsAreAllowed(): void
    {
        $missing = RuleVersionRecord::fromClock($this->versionRecord([]), 'damage');
        $null = RuleVersionRecord::fromClock($this->versionRecord(['mechanics' => null]), 'damage');

        self::assertSame([], $missing->getMechanics());
        self::assertSame([], $null->getMechanics());
    }

    /**
     * Корректная строка mechanics сохраняется при гидратации.
     *
     * @return void
     */
    public function testValidMechanicRowIsHydrated(): void
    {
        $mechanics = [[
            'mechanic_id' => 7,
            'mechanic_payload' => [],
        ]];

        $record = RuleVersionRecord::fromClock($this->versionRecord(['mechanics' => $mechanics]), 'damage');

        self::assertSame($mechanics, $record->getMechanics());
    }

    /**
     * Вложенные объекты с нулевыми ключами остаются объектами при разборе spec.
     *
     * @return void
     */
    public function testNestedObjectMapsAreKeptForSpecRead(): void
    {
        $decoded = JsonDocuments::decode(
            '{"type":"trait","zones":{"or":{"kind":"parameter_sum_tables","max_level":9,'
            . '"tables":{"strength":{"0":0,"1":2,"2":4}}}}}',
        );
        self::assertInstanceOf(JsonObject::class, $decoded);

        $record = RuleVersionRecord::fromClock($this->versionRecord([
            'type' => 'ability',
            'spec' => $decoded,
        ]), 'trait');

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(JsonObject::class, $record->getSpecSource()['zones']);
        self::assertSame([
            'type' => 'trait',
            'zones' => ['or' => [
                'kind' => 'parameter_sum_tables',
                'max_level' => 9,
                'tables' => ['strength' => ['0' => 0, '1' => 2, '2' => 4]],
            ]],
        ], $record->getSpecDocument());
    }

    /**
     * Вложенные JsonObject из кэша разворачиваются в spec и mechanics.
     *
     * @return void
     */
    public function testNestedJsonObjectsAreUnwrapped(): void
    {
        $spec = new JsonObject([
            'category' => 'armor',
            'armor' => new JsonObject([
                'resistance_slots' => [[
                    'damage_type_code' => 'cut',
                    'value' => new JsonObject(['base' => 3, 'size' => 0]),
                    'durability' => 1,
                ]],
            ]),
        ]);
        $mechanics = [new JsonObject([
            'mechanic_id' => 7,
            'mechanic_payload' => new JsonObject([]),
        ])];

        $record = RuleVersionRecord::fromClock($this->versionRecord([
            'type' => 'item',
            'spec' => $spec,
            'mechanics' => $mechanics,
        ]), 'mail');

        self::assertFalse($record->isSpecBroken());
        self::assertSame([
            'category' => 'armor',
            'armor' => [
                'resistance_slots' => [[
                    'damage_type_code' => 'cut',
                    'value' => ['base' => 3, 'size' => 0],
                    'durability' => 1,
                ]],
            ],
        ], $record->getSpecDocument());
        self::assertSame([[
            'mechanic_id' => 7,
            'mechanic_payload' => [],
        ]], $record->getMechanics());
    }

    /**
     * Невалидные container, row, id и payload не пропускаются.
     *
     * @param mixed $mechanics Mechanics из записи.
     *
     * @return void
     *
     * @throws RuleInvalidException Если строка mechanics битая.
     *
     * @dataProvider malformedMechanicsProvider
     */
    public function testMalformedMechanicsAreRejected(mixed $mechanics): void
    {
        $this->expectException(RuleInvalidException::class);

        RuleVersionRecord::fromClock($this->versionRecord(['mechanics' => $mechanics]), 'damage');
    }

    /**
     * Набор битых форм mechanics.
     *
     * @return array<string, array{mixed}> Cases.
     */
    public static function malformedMechanicsProvider(): array
    {
        return [
            'associative container' => [['row' => []]],
            'malformed row' => [[[]]],
            'missing mechanic id' => [[['mechanic_payload' => []]]],
            'zero mechanic id' => [[['mechanic_id' => 0, 'mechanic_payload' => []]]],
            'negative mechanic id' => [[['mechanic_id' => -1, 'mechanic_payload' => []]]],
            'string mechanic id' => [[['mechanic_id' => '7', 'mechanic_payload' => []]]],
            'missing payload' => [[['mechanic_id' => 7]]],
            'null payload' => [[['mechanic_id' => 7, 'mechanic_payload' => null]]],
            'scalar payload' => [[['mechanic_id' => 7, 'mechanic_payload' => 'payload']]],
        ];
    }

    /**
     * Создаёт конверт версии с заданным полем mechanics.
     *
     * @param array<string, mixed> $fields Дополнительные поля.
     *
     * @return VersionRecord Версия.
     */
    private function versionRecord(array $fields): VersionRecord
    {
        return new VersionRecord(
            1,
            1,
            true,
            array_merge([
                'type' => 'damage_type',
                'name' => 'Damage',
                'description' => '',
                'spec' => [],
                'keywords' => [],
                'content_status' => 'needs_work',
                'content_note' => '',
            ], $fields),
            DateTime::now(),
        );
    }
}
