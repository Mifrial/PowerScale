<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
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
