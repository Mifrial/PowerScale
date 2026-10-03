<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MoneyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\CharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ParameterScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use PHPUnit\Framework\TestCase;

/**
 * Разбор spec версии: целый документ, битая форма, тип без spec.
 */
final class RuleSpecTest extends TestCase
{
    /**
     * Урезанная раса остаётся целой spec с пустыми недостающими полями.
     *
     * @return void
     */
    public function testPartialRaceSpecIsParsed(): void
    {
        $record = $this->record('race', ['abilities' => [['ability_code' => 'hearing', 'automatic' => false]]]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(RaceSpec::class, $spec);
        self::assertSame(0, $spec->getCostOs());
        self::assertSame('hearing', $spec->getAbilities()[0]->getAbilityCode());
    }

    /**
     * Чужой тип ключа помечает только эту версию.
     *
     * @return void
     */
    public function testWrongKeyShapeMarksOnlyThatVersion(): void
    {
        $broken = $this->record('ability', ['grants' => 'nope']);
        $whole = $this->record('ability', []);

        self::assertTrue($broken->isSpecBroken());
        self::assertNull($broken->getSpec());
        self::assertSame(['grants' => 'nope'], $broken->getSpecDocument());
        self::assertInstanceOf(AbilityPlainSpec::class, $whole->getSpec());
        self::assertFalse($whole->isSpecBroken());
    }

    /**
     * simple не несёт spec и не считается битым.
     *
     * @return void
     */
    public function testSimpleHasNoSpecContract(): void
    {
        $record = $this->record('simple', ['extra' => 1]);

        self::assertFalse($record->isSpecBroken());
        self::assertNull($record->getSpec());
        self::assertSame(['extra' => 1], $record->getSpecDocument());
    }

    /**
     * Лимит «ловкость не выше силы» не делает предмет битым.
     *
     * @return void
     */
    public function testCharacteristicLimitKeepsItemSpec(): void
    {
        $record = $this->record('item', [
            'armor' => [
                'strength_penalty' => -1,
                'characteristic_limits' => [[
                    'characteristic_code' => 'agility',
                    'limit' => ['type' => 'characteristic', 'characteristic_code' => 'strength', 'modifier' => 0],
                ]],
            ],
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(ItemSpec::class, $spec);
        $limit = $spec->getArmor()->getCharacteristicLimits()[0]->getLimit();
        self::assertInstanceOf(CharacteristicNode::class, $limit);
    }

    /**
     * Денежный грант читает fixed, percent и apply.
     *
     * @return void
     */
    public function testMoneyGrantReadsFixed(): void
    {
        $record = $this->record('ability', [
            'grants' => [['level' => 1, 'grants' => [['type' => 'money', 'fixed' => 40, 'percent' => 0, 'apply' => 'max']]]],
        ]);
        $spec = $record->getSpec();

        self::assertInstanceOf(AbilityPlainSpec::class, $spec);
        $grant = $spec->getGrantBlocks()[0]->getGrants()[0];
        self::assertInstanceOf(MoneyGrant::class, $grant);
        self::assertSame(40, $grant->getFixed());
    }

    /**
     * Скалярный amount хранится узлом, не целым.
     *
     * @return void
     */
    public function testScalarAmountStaysANode(): void
    {
        $record = $this->record('ability', [
            'grants' => [[
                'level' => 1,
                'grants' => [[
                    'type' => 'characteristic_modify',
                    'characteristic_code' => 'strength',
                    'source_code' => 'age',
                    'amount' => ['type' => 'parameter', 'parameter_code' => 'x', 'per_unit' => 2],
                ]],
            ]],
        ]);
        $spec = $record->getSpec();
        $grant = $spec->getGrantBlocks()[0]->getGrants()[0];

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(CharacteristicModifyGrant::class, $grant);
        self::assertInstanceOf(ParameterScalar::class, $grant->getAmount());
        self::assertSame(2, $grant->getAmount()->getPerUnit());
    }

    /**
     * Запись среза.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $spec Документ.
     *
     * @return RuleVersionRecord Версия.
     */
    private function record(string $type, array $spec): RuleVersionRecord
    {
        return new RuleVersionRecord(1, 1, 'code', true, $type, 'Name', '', $spec, [], [], 'needs_work', '', DateTime::now());
    }
}
