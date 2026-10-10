<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\DimensionalResourceValue;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет native typed resource storage boundary.
 */
final class CharacterResourceStorageTest extends TestCase
{
    /**
     * Checks scalar and dimensional native round-trips.
     *
     * @return void
     */
    public function testNativeRoundTripKeepsVariants(): void
    {
        $storage = new CharacterResourceStorage();
        $rows = $storage->parseRows(
            [
                ['ruleCode' => 'action-points', 'current' => 3],
                ['ruleCode' => 'qi', 'current' => ['base' => 2, 'size' => 1]],
            ],
            $this->slice(),
        );

        self::assertSame(
            [
                ['ruleCode' => 'action-points', 'current' => 3],
                ['ruleCode' => 'qi', 'current' => ['base' => 2, 'size' => 1]],
            ],
            $storage->serializeRows($rows),
        );
    }

    /**
     * Rejects duplicates, unknown rules and implicit conversions.
     *
     * @return void
     */
    public function testInvalidRowsAreRejected(): void
    {
        $storage = new CharacterResourceStorage();
        $slice = $this->slice();

        $this->expectException(CharacterInvalidException::class);
        $storage->parseRows(
            [
                ['ruleCode' => 'action-points', 'current' => 1],
                ['ruleCode' => 'action-points', 'current' => 2],
            ],
            $slice,
        );
    }

    /**
     * Rejects scalar values for a dimensional live rule.
     *
     * @return void
     */
    public function testDimensionalShapeIsStrict(): void
    {
        $storage = new CharacterResourceStorage();

        $this->expectException(CharacterInvalidException::class);
        $storage->parseRows(
            [['ruleCode' => 'qi', 'current' => 2]],
            $this->slice(),
        );
    }

    /**
     * Rejects unknown resource codes and authoritative extra fields.
     *
     * @return void
     */
    public function testUnknownAndExtraFieldsAreRejected(): void
    {
        $storage = new CharacterResourceStorage();
        $slice = $this->slice();

        try {
            $storage->parseRows(
                [['ruleCode' => 'removed', 'current' => 1]],
                $slice,
            );
            self::fail('Unknown resource code was accepted');
        } catch (CharacterInvalidException) {
            self::assertTrue(true);
        }

        $this->expectException(CharacterInvalidException::class);
        $storage->parseRows(
            [['ruleCode' => 'action-points', 'current' => 1, 'limit' => 5]],
            $slice,
        );
    }

    /**
     * Списывает scalar и dimensional значения в native форме.
     *
     * @return void
     */
    public function testSpendKeepsNativeVariants(): void
    {
        $storage = new CharacterResourceStorage();
        $slice = $this->slice();
        $rows = $storage->parseRows([
            ['ruleCode' => 'action-points', 'current' => 5],
            ['ruleCode' => 'qi', 'current' => ['base' => 4, 'size' => 1]],
        ], $slice);

        $spent = $storage->spendRows($rows, $slice, [
            new ResourceSpend('action-points', new ScalarResourceValue(2)),
            new ResourceSpend('qi', new DimensionalResourceValue(new DimensionalNumber(1, 0))),
        ]);

        self::assertSame([
            ['ruleCode' => 'action-points', 'current' => 3],
            ['ruleCode' => 'qi', 'current' => ['base' => 7, 'size' => 0]],
        ], $storage->serializeRows($spent));
    }

    /**
     * Недостаток одного компонента не оставляет частично подготовленный spend.
     *
     * @return void
     */
    public function testMultiSpendFailureIsAtomic(): void
    {
        $storage = new CharacterResourceStorage();
        $slice = $this->slice();
        $rows = $storage->parseRows([
            ['ruleCode' => 'action-points', 'current' => 5],
            ['ruleCode' => 'qi', 'current' => ['base' => 1, 'size' => 0]],
        ], $slice);

        try {
            $storage->spendRows($rows, $slice, [
                new ResourceSpend('action-points', new ScalarResourceValue(2)),
                new ResourceSpend('qi', new DimensionalResourceValue(new DimensionalNumber(2, 0))),
            ]);
            self::fail('Insufficient component must reject whole spend');
        } catch (CharacterInvalidException) {
            self::assertSame([
                ['ruleCode' => 'action-points', 'current' => 5],
                ['ruleCode' => 'qi', 'current' => ['base' => 1, 'size' => 0]],
            ], $storage->serializeRows($rows));
        }
    }

    /**
     * Creates a minimal live slice with both resource variants.
     *
     * @return CharacterRuleSlice Live slice.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(
            1,
            1,
            'space',
            [
                $this->rule('action-points', false, 5),
                $this->rule('qi', true, ['base' => 2, 'size' => 1]),
            ],
            [],
        );
    }

    /**
     * Creates a resource rule.
     *
     * @param string $code Rule code.
     * @param bool $dimensional Variant.
     * @param int|array{base: int, size: int} $base Limit base.
     *
     * @return CharacterResolvedRule Resolved rule.
     */
    private function rule(string $code, bool $dimensional, int|array $base): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                'resource',
                $code,
                '',
                [
                    'is_dimensional' => $dimensional,
                    'auto_add' => true,
                    'check_token' => false,
                    'limit' => [
                        'base' => $base,
                        'adjustments' => [],
                    ],
                ],
                [],
                [],
                'published',
                '',
                DateTime::fromUnix(0),
            ),
            [],
        );
    }
}
