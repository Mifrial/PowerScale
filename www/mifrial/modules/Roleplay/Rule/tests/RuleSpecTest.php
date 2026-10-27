<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Core\Kernel\Http\JsonDocuments;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Kernel\Value\JsonObject;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MoneyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\MovementOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterSumTablesZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterTableZone;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\CharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\CurrentMovementStepNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalNode;
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
     * Признак снаряда читается из spec предмета.
     *
     * @return void
     */
    public function testProjectileFlagIsParsed(): void
    {
        $record = $this->record('item', [
            'category' => 'equipment',
            'projectile' => true,
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(ItemSpec::class, $spec);
        self::assertTrue($spec->isProjectile());
    }

    /**
     * Оружие читает альтернативные наборы ключевых слов боеприпасов.
     *
     * @return void
     */
    public function testWeaponReadsAmmunitionRequirements(): void
    {
        $record = $this->record('item', [
            'category' => 'weapon',
            'weapon' => [
                'weapon_profiles' => [],
                'ammunition_requirements' => [
                    ['keyword_codes' => ['item', 'projectile', 'arrow']],
                    ['keyword_codes' => ['item', 'bolt']],
                ],
            ],
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(ItemSpec::class, $spec);
        self::assertSame(
            [['item', 'projectile', 'arrow'], ['item', 'bolt']],
            $spec->getWeapon()?->getAmmunitionRequirements(),
        );
    }

    /**
     * Пустой набор боеприпасов ломает spec, а не принимает любой боеприпас.
     *
     * @return void
     */
    public function testEmptyAmmunitionRequirementBreaksItemSpec(): void
    {
        $record = $this->record('item', [
            'category' => 'weapon',
            'weapon' => [
                'weapon_profiles' => [],
                'ammunition_requirements' => [['keyword_codes' => []]],
            ],
        ]);

        self::assertTrue($record->isSpecBroken());
        self::assertNull($record->getSpec());
    }

    /**
     * Плоский список grants предмета читается.
     *
     * @return void
     */
    public function testItemGrantsAreFlat(): void
    {
        $record = $this->record('item', [
            'category' => 'equipment',
            'innate' => false,
            'special_rule_codes' => [],
            'grants' => [[
                'type' => 'characteristic_modify',
                'characteristic_code' => 'strength',
                'source_code' => 'mail',
                'check_codes' => [],
                'amount' => -1,
                'permanent' => true,
            ]],
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(ItemSpec::class, $spec);
        $grant = $spec->getGrants()[0];
        self::assertInstanceOf(CharacteristicModifyGrant::class, $grant);
        self::assertSame('strength', $grant->getCharacteristicCode());
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
     * Нет ключа initiative — проверка без инициативы.
     *
     * @return void
     */
    public function testCheckWithoutInitiativeKeyIsNotInitiative(): void
    {
        $spec = $this->check(['allowed_modes' => 'solo']);

        self::assertFalse($spec->isInitiative());
    }

    /**
     * Явный false не задаёт инициативу.
     *
     * @return void
     */
    public function testCheckInitiativeFalseStaysFalse(): void
    {
        $spec = $this->check(['allowed_modes' => 'solo', 'initiative' => false]);

        self::assertFalse($spec->isInitiative());
    }

    /**
     * initiative true — эта проверка задаёт инициативу.
     *
     * @return void
     */
    public function testCheckInitiativeTrueIsInitiative(): void
    {
        $spec = $this->check(['allowed_modes' => 'solo', 'initiative' => true]);

        self::assertTrue($spec->isInitiative());
    }

    /**
     * Не bool в initiative бьёт только эту версию.
     *
     * @return void
     */
    public function testCheckInitiativeWrongTypeMarksVersionBroken(): void
    {
        $document = ['allowed_modes' => 'solo', 'initiative' => 'yes'];
        $record = $this->record('check', $document);

        self::assertTrue($record->isSpecBroken());
        self::assertNull($record->getSpec());
        self::assertSame($document, $record->getSpecDocument());
    }

    /**
     * Карточка действия с kind зон и action_components остаётся целой.
     *
     * @return void
     */
    public function testActionSpecAcceptsKindZonesAndActionComponents(): void
    {
        $record = $this->record('ability', [
            'type' => 'action',
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
            'requirements' => [],
            'grants' => [],
            'parent_ability_code' => null,
            'action_components' => [[
                'type' => 'resource',
                'resource_code' => 'action-points',
                'amount' => 2,
                'label' => 'Действие',
            ]],
            'action_effects' => [
                [
                    'type' => 'current_action_attack_accuracy',
                    'delta' => -1,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ],
                [
                    'type' => 'next_action_attack_cost',
                    'resource_code' => 'action-points',
                    'delta' => 1,
                ],
            ],
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(AbilityActionSpec::class, $spec);
        self::assertCount(1, $spec->getComponents());
    }

    /**
     * Врождённая черта с моков: scalar-параметр парой и таблица costs.
     *
     * @return void
     */
    public function testInnateTraitMockSpecIsParsed(): void
    {
        $record = $this->record('ability', [
            'type' => 'trait',
            'zones' => [
                'os' => [
                    'kind' => 'parameter_table',
                    'parameter_code' => 'x',
                    'costs' => ['-3' => -3, '-2' => -2, '-1' => -1, '1' => 2, '2' => 4, '3' => 8],
                ],
            ],
            'requirements' => [],
            'grants' => [[
                'level' => 1,
                'grants' => [[
                    'type' => 'characteristic_modify',
                    'characteristic_code' => 'strength',
                    'amount' => ['type' => 'parameter', 'parameter_code' => 'x', 'per_unit' => 1],
                    'source_code' => 'innate',
                ]],
            ]],
            'parent_ability_code' => null,
            'parameters' => [[
                'code' => 'x',
                'label' => 'X',
                'kind' => 'scalar',
                'resolution' => 'purchase',
                'default' => 0,
                'min' => -3,
                'max' => 3,
                'linked' => [
                    'ability_code' => 'innate-endurance',
                    'parameter_code' => 'x',
                    'max_delta' => 3,
                ],
            ]],
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(AbilityPlainSpec::class, $spec);
        $os = $spec->getBase()->getZones()['os'] ?? null;
        self::assertInstanceOf(ParameterTableZone::class, $os);
        self::assertSame(-3, $os->getTable()['-3']);
        self::assertSame(0, $spec->getBase()->getParameters()[0]->getDefault());
    }

    /**
     * JSON-ключи таблицы costs приходят целыми.
     *
     * @return void
     */
    public function testParameterTableCostsAcceptIntegerJsonKeys(): void
    {
        $record = $this->record('ability', [
            'type' => 'trait',
            'zones' => [
                'os' => [
                    'kind' => 'parameter_table',
                    'parameter_code' => 'x',
                    'costs' => [1 => 2, 2 => 4],
                ],
            ],
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(AbilityPlainSpec::class, $spec);
        $os = $spec->getBase()->getZones()['os'] ?? null;
        self::assertInstanceOf(ParameterTableZone::class, $os);
        self::assertSame(2, $os->getTable()['1']);
        self::assertSame(4, $os->getTable()['2']);
    }

    /**
     * Пустые zones grant-only способности целые.
     *
     * @return void
     */
    public function testEmptyZonesKeepAbilitySpec(): void
    {
        $record = $this->record('ability', ['type' => 'trait', 'zones' => []]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(AbilityPlainSpec::class, $spec);
        self::assertSame([], $spec->getBase()->getZones());
    }

    /**
     * Таблица costs с ключом 0 остаётся объектом.
     *
     * @return void
     */
    public function testParameterSumTablesKeepZeroCostKeys(): void
    {
        $decoded = JsonDocuments::decode(
            '{"type":"trait","zones":{"or":{"kind":"parameter_sum_tables","max_level":9,'
            . '"tables":{"strength":{"0":0,"1":2,"2":4}}}}}',
        );
        self::assertInstanceOf(JsonObject::class, $decoded);
        $record = $this->record('ability', $decoded->getMembers());
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(AbilityPlainSpec::class, $spec);
        $or = $spec->getBase()->getZones()['or'] ?? null;
        self::assertInstanceOf(ParameterSumTablesZone::class, $or);
        self::assertSame(0, $or->getTables()['strength']['0']);
        self::assertSame(2, $or->getTables()['strength']['1']);
    }

    /**
     * Параметры расы {base,size} после JSON остаются объектом.
     *
     * @return void
     */
    public function testRaceParametersKeepJsonObjects(): void
    {
        $decoded = JsonDocuments::decode(
            '{"abilities":[{"ability_code":"magic-core-capacity","automatic":false,'
            . '"parameters":{"x":{"base":4,"size":0}}}]}',
        );
        self::assertInstanceOf(JsonObject::class, $decoded);
        $record = $this->record('race', $decoded->getMembers());
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(RaceSpec::class, $spec);
        self::assertSame(4, $spec->getAbilities()[0]->getParameters()['x']->getBase());
    }

    /**
     * automatic объектом и amount chosen после JSON.
     *
     * @return void
     */
    public function testCharacteristicAutomaticAndChosenAmountKeepJsonObjects(): void
    {
        $combat = JsonDocuments::decode(
            '{"group":"combat","automatic":{"value":{"base":3,"size":-1}}}',
        );
        self::assertInstanceOf(JsonObject::class, $combat);
        $combatRecord = $this->record('characteristic', $combat->getMembers());
        self::assertFalse($combatRecord->isSpecBroken());

        $wait = JsonDocuments::decode(
            '{"type":"action","action_components":[{"type":"resource","resource_code":"action-points",'
            . '"amount":{"type":"chosen","max":"available"}}]}',
        );
        self::assertInstanceOf(JsonObject::class, $wait);
        $waitRecord = $this->record('ability', $wait->getMembers());
        self::assertFalse($waitRecord->isSpecBroken());
        self::assertInstanceOf(AbilityActionSpec::class, $waitRecord->getSpec());
    }

    /**
     * Дробный вес в base — битая spec.
     *
     * @return void
     */
    public function testItemWeightFloatBaseMarksVersionBroken(): void
    {
        $record = $this->record('item', [
            'category' => 'equipment',
            'weight' => ['base' => 3.5, 'size' => 0],
        ]);

        self::assertTrue($record->isSpecBroken());
        self::assertNull($record->getSpec());
    }

    /**
     * Ходьба: направления и вилка DimensionalFormula.
     *
     * @return void
     */
    public function testWalkMovementOperationIsParsed(): void
    {
        $record = $this->record('ability', [
            'type' => 'action',
            'zones' => ['or' => ['kind' => 'automatic']],
            'operations' => [[
                'type' => 'movement',
                'allowedDirections' => ['horizontal' => ['front'], 'vertical' => []],
                'distance' => [
                    'horizontal' => [
                        'min' => ['type' => 'steps', 'count' => 0],
                        'max' => ['type' => 'current_movement_step', 'multiplier' => 1],
                        'minInclusive' => false,
                        'maxInclusive' => true,
                    ],
                ],
                'freeTurn' => ['maxDegrees' => 90, 'free' => true],
            ]],
        ]);
        $spec = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(AbilityActionSpec::class, $spec);
        $operation = $spec->getOperations()[0] ?? null;
        self::assertInstanceOf(MovementOperation::class, $operation);
        self::assertSame(['front'], $operation->getHorizontal());
        $axis = $operation->findHorizontalDistance();
        self::assertNotNull($axis);
        self::assertInstanceOf(DimensionalNode::class, $axis->getMin());
        self::assertInstanceOf(CurrentMovementStepNode::class, $axis->getMax());
        self::assertSame(90, $operation->getMaxDegrees());
        self::assertTrue($operation->isFree());
    }

    /**
     * Scalar-параметр с парой base/size — битая spec.
     *
     * @return void
     */
    public function testScalarParameterPairMarksVersionBroken(): void
    {
        $record = $this->record('ability', [
            'type' => 'trait',
            'parameters' => [[
                'code' => 'x',
                'label' => 'X',
                'kind' => 'scalar',
                'resolution' => 'purchase',
                'default' => ['base' => 0, 'size' => 0],
            ]],
        ]);

        self::assertTrue($record->isSpecBroken());
        self::assertNull($record->getSpec());
    }

    /**
     * Целая проверка.
     *
     * @param array<string, mixed> $spec Документ.
     *
     * @return CheckSpec Spec.
     */
    private function check(array $spec): CheckSpec
    {
        $record = $this->record('check', $spec);
        $parsed = $record->getSpec();

        self::assertFalse($record->isSpecBroken());
        self::assertInstanceOf(CheckSpec::class, $parsed);

        return $parsed;
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
