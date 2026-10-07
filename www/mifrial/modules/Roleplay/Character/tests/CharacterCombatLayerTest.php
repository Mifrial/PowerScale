<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Service\CharacterCombatLayers;
use Mifrial\Roleplay\Character\Service\CharacterFormulaContexts;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use PHPUnit\Framework\TestCase;

/**
 * Проекция слоёв брони, блока и грантов. Строку персонажа не открывает.
 */
final class CharacterCombatLayerTest extends TestCase
{
    /**
     * Надетая броня берётся из spec. Снятое, custom и equippedModifiers слоёв не дают.
     *
     * @return void
     */
    public function testEquippedArmorComesFromRevision(): void
    {
        $layers = $this->project($this->rules(), [
            'inventory' => [
                ['id' => 1, 'ruleCode' => 'mail', 'equipped' => true],
                ['id' => 2, 'ruleCode' => 'mail', 'equipped' => false],
                ['id' => 3, 'ruleCode' => 'blade', 'equipped' => true],
                ['equipped' => true, 'custom' => ['name' => 'rag']],
            ],
        ], null);

        self::assertCount(2, $layers);
        self::assertSame('defense', $layers[0]->getKind());
        self::assertSame(2, $layers[0]->getValue()->getBase());
        self::assertSame(4, $layers[0]->getDurability());
        self::assertSame('resistance', $layers[1]->getKind());
        self::assertNull($layers[1]->getDurability());
        self::assertSame('fire', $layers[1]->getDamageTypeCode());
    }

    /**
     * Пустые модификаторы оставляют базовый spec. Неизвестный код отклоняется.
     *
     * @return void
     */
    public function testMissingModifierCodeIsRejected(): void
    {
        $base = $this->project($this->rules(), [
            'inventory' => [['id' => 1, 'ruleCode' => 'mail', 'equipped' => true, 'modifiers' => []]],
        ], null);
        self::assertSame(2, $base[0]->getValue()->getBase());

        $this->expectException(CharacterInvalidException::class);
        $this->project($this->rules(), [
            'inventory' => [['id' => 1, 'ruleCode' => 'mail', 'equipped' => true, 'modifiers' => ['missing']]],
        ], null);
    }

    /**
     * Блок берётся у строки с переданным id. Свой source сохраняется, пустой становится «От блокирования».
     *
     * @return void
     */
    public function testBlockUsesSelectedRow(): void
    {
        $kept = $this->project($this->rules(), [
            'inventory' => [
                ['id' => 7, 'ruleCode' => 'guard', 'equipped' => true],
                ['id' => 8, 'ruleCode' => 'guard', 'equipped' => true, 'modifiers' => ['heavy']],
            ],
        ], 7);
        $changed = $this->project($this->rules(), [
            'inventory' => [
                ['id' => 7, 'ruleCode' => 'guard', 'equipped' => true],
                ['id' => 8, 'ruleCode' => 'guard', 'equipped' => true, 'modifiers' => ['heavy']],
            ],
        ], 8);

        self::assertSame(3, $this->blockDefense($kept)->getValue()->getBase());
        self::assertSame('От блокирования', $this->blockDefense($kept)->getSourceCode());
        self::assertSame(5, $this->blockDefense($changed)->getValue()->getBase());
        self::assertSame('От блокирования', $changed[1]->getSourceCode());
        self::assertSame('ward', $changed[2]->getSourceCode());
    }

    /**
     * Нет профиля, повтор id и ненадетый блок отклоняются.
     *
     * @return void
     */
    public function testBadBlockIsRejected(): void
    {
        $rules = $this->rules();
        $this->expectException(CharacterInvalidException::class);
        $this->project($rules, [
            'inventory' => [
                ['id' => 1, 'ruleCode' => 'blade', 'equipped' => true],
                ['id' => 1, 'ruleCode' => 'guard', 'equipped' => true],
            ],
        ], 1);
    }

    /**
     * Грант сопротивления и параметр формулы. Одинаковые документы дают один список.
     *
     * @return void
     */
    public function testGrantUsesAbilityParameter(): void
    {
        $choices = [
            'inventory' => [],
            'abilities' => [[
                'ruleCode' => 'ward',
                'level' => 1,
                'parameters' => ['x' => ['base' => 3, 'size' => 0]],
            ]],
        ];
        $sheet = $this->sheet(['ward' => 1]);
        $player = $this->layers()->project(1, 1, $sheet, $choices);
        $npc = $this->layers()->project(1, 1, $sheet, $choices);

        self::assertCount(1, $player);
        self::assertSame('body', $player[0]->getSourceCode());
        self::assertSame(6, $player[0]->getValue()->getBase());
        self::assertSame(0, $player[0]->getValue()->getSize());
        self::assertNull($player[0]->getDurability());
        self::assertEquals($player, $npc);
    }

    /**
     * Нет требуемого параметра — отказ, не ноль.
     *
     * @return void
     */
    public function testMissingParameterIsRejected(): void
    {
        $this->expectException(CharacterInvalidException::class);
        $this->project($this->rules(), ['inventory' => [], 'abilities' => []], null, ['ward' => 1]);
    }

    /**
     * Проекция не читает строку персонажа.
     *
     * @return void
     */
    public function testProjectionDoesNotReadCharacterRow(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->expects(self::never())->method('get');
        $layers = new CharacterCombatLayers(
            $this->slicePort($this->rules()),
            new CharacterFormulaContexts($characters),
            new FormulaEvaluations(),
        );

        $layers->project(1, 1, $this->sheet([]), ['inventory' => []]);
    }

    /**
     * Защита блока.
     *
     * @param array<int, CharacterCombatLayer> $layers Слои.
     *
     * @return CharacterCombatLayer Слой.
     */
    private function blockDefense(array $layers): CharacterCombatLayer
    {
        foreach ($layers as $layer) {
            if ($layer->getKind() === 'defense' && $layer->getDurability() === null && $layer->getSourceCode() !== '') {
                return $layer;
            }
        }

        self::fail('Block defense is missing');
    }

    /**
     * Проекция на срезе.
     *
     * @param array<int, CharacterResolvedRule> $rules Правила.
     * @param array<string, mixed> $choices Выборы.
     * @param int|null $blockId Блок.
     * @param array<string, int> $levels Уровни.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     */
    private function project(array $rules, array $choices, ?int $blockId, array $levels = []): array
    {
        return $this->layers($rules)->project(1, 1, $this->sheet($levels), $choices, $blockId);
    }

    /**
     * Порт на правилах.
     *
     * @param array<int, CharacterResolvedRule> $rules Правила.
     *
     * @return CharacterCombatLayers Порт.
     */
    private function layers(array $rules = []): CharacterCombatLayers
    {
        return new CharacterCombatLayers(
            $this->slicePort($rules === [] ? $this->rules() : $rules),
            new CharacterFormulaContexts($this->createMock(ICharacters::class)),
            new FormulaEvaluations(),
        );
    }

    /**
     * Срез отдаёт переданные правила.
     *
     * @param array<int, CharacterResolvedRule> $rules Правила.
     *
     * @return ICharacterRuleSlices Порт.
     */
    private function slicePort(array $rules): ICharacterRuleSlices
    {
        return new class ($rules) implements ICharacterRuleSlices {
            /**
             * @param array<int, CharacterResolvedRule> $rules Правила.
             */
            public function __construct(private readonly array $rules)
            {
            }

            /**
             * @param int $spaceId Мир.
             * @param int $revision Ревизия.
             *
             * @return CharacterRuleSlice Срез.
             */
            public function get(int $spaceId, int $revision): CharacterRuleSlice
            {
                return new CharacterRuleSlice($spaceId, $revision, 'world', $this->rules, []);
            }
        };
    }

    /**
     * Предметы, модификатор и способность.
     *
     * @return array<int, CharacterResolvedRule> Правила.
     */
    private function rules(): array
    {
        return [
            $this->rule('mail', 'item', [
                'category' => 'equipment',
                'armor' => [
                    'defense_slots' => [['defense' => ['base' => 2, 'size' => 0], 'durability' => 4, 'source_code' => 'mail']],
                    'resistance_slots' => [['damage_type_code' => 'fire', 'value' => ['base' => 1, 'size' => 0]]],
                ],
            ], ['armor-item']),
            $this->rule('blade', 'item', ['category' => 'equipment'], []),
            $this->rule('guard', 'item', [
                'category' => 'equipment',
                'block_profile' => [
                    'efficiency' => ['base' => 1, 'size' => 0],
                    'defense' => ['base' => 3, 'size' => 0],
                    'resistances' => [[
                        'damage_type_code' => 'cut',
                        'value' => ['base' => 1, 'size' => 0],
                        'source_code' => '',
                    ], [
                        'damage_type_code' => 'fire',
                        'value' => ['base' => 1, 'size' => 0],
                        'source_code' => 'ward',
                    ]],
                ],
            ], ['shield-item']),
            $this->rule('heavy', 'item_modifier', [
                'type_code' => 'heavy',
                'operations' => [[
                    'type' => 'block',
                    'add' => 2,
                    'when' => ['keyword_any' => ['shield-item']],
                ]],
            ], []),
            $this->rule('ward', 'ability', [
                'type' => 'trait',
                'grants' => [[
                    'level' => 1,
                    'grants' => [[
                        'type' => 'resistance',
                        'damage_type_code' => 'magic',
                        'source_code' => 'body',
                        'value' => ['type' => 'parameter', 'parameter_code' => 'x', 'per_unit' => 2],
                    ]],
                ]],
            ], []),
        ];
    }

    /**
     * Правило среза.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Документ.
     * @param array<int, string> $keywords Признаки.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec, array $keywords): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(1, 1, $code, true, $type, 'Name', '', $spec, [], [], 'needs_work', '', DateTime::now()),
            $keywords,
        );
    }

    /**
     * Лист с уровнями.
     *
     * @param array<string, int> $levels Уровни.
     *
     * @return array<string, mixed> Документ.
     */
    private function sheet(array $levels): array
    {
        return [
            'abilityLevels' => $levels,
            'characteristicPurchases' => [],
            'equippedModifiers' => [['itemCode' => 'mail']],
        ];
    }
}
