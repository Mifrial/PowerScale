<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterCharacteristicPurchase;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterCustomRule;
use Mifrial\Roleplay\Character\Dto\CharacterInventoryChoice;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\CharacterOsSteps;
use Mifrial\Roleplay\Character\Service\CharacterSheets;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterActiveInput;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

final class CharacterSheetsTest extends TestCase
{
    /**
     * Пара не зависит от порядка массива. Пустой путь ничего не покрывает.
     * Включённый путь покрывает транзитивно. Каталог расы не пишет уровень.
     *
     * @return void
     */
    public function testPairsPathsAndRacialCatalog(): void
    {
        $sheets = $this->sheets($this->createMock(IMechanics::class));
        $validation = $sheets->validate($this->slice(), $this->choices());

        self::assertSame([], $this->codes($validation->getProblems()));
        self::assertSame([
            'feature' => 1,
            'bolt' => 1,
            'open' => 1,
            'resist' => 3,
        ], $validation->getAbilityLevels());
        self::assertSame(['resist', 'hearing'], $validation->getRacialAbilityCodes());
        self::assertSame(['psionic'], $validation->getCoveredPaths()['bolt:psionic:magic-path']);
        self::assertSame([], $validation->getCoveredPaths()['open:other:magic-path']);
    }

    /**
     * Два free в одной паре — отказ. Индекс массива роли не назначает.
     *
     * @return void
     */
    public function testTwoFreeRolesAreAProblem(): void
    {
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('a', 1, '', '', null, 'p', 'free'),
            $this->ability('b', 1, '', '', null, 'p', 'free'),
        ], [], [], [], true);
        $validation = $this->sheets($this->createMock(IMechanics::class))->validate($this->slice(), $choices);

        self::assertContains('CHARACTER_STUDY_PAIR', $this->codes($validation->getProblems()));
    }

    /**
     * Два skill_study и два денежных гранта без указателя — отказы.
     *
     * @return void
     */
    public function testAmbiguousSkillAndMoneyGrants(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['abilities' => []]),
            $this->rule('lore', 'ability', ['grants' => [[
                'level' => 1,
                'grants' => [
                    ['type' => 'skill_study', 'ability_codes' => ['script'], 'max_level' => 1, 'paid_cost' => 0],
                    ['type' => 'money', 'fixed' => 1, 'percent' => 0, 'apply' => 'shop'],
                ],
            ]]]),
            $this->rule('lore-2', 'ability', ['grants' => [[
                'level' => 1,
                'grants' => [
                    ['type' => 'skill_study', 'ability_codes' => ['script'], 'max_level' => 1, 'paid_cost' => 0],
                    ['type' => 'money', 'fixed' => 2, 'percent' => 0, 'apply' => 'shop'],
                ],
            ]]]),
            $this->rule('script', 'ability', []),
        ], []);
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('lore', 1, '', '', null, null, null),
            $this->ability('lore-2', 1, '', '', null, null, null),
            $this->ability('script', 1, '', '', null, null, null),
        ], [], [], [], true);
        $codes = $this->codes($this->sheets($this->createMock(IMechanics::class))->validate($slice, $choices)->getProblems());

        self::assertContains('CHARACTER_GRANT', $codes);
        self::assertGreaterThanOrEqual(2, count(array_filter(
            $codes,
            static fn (string $code): bool => $code === 'CHARACTER_GRANT',
        )));
    }

    /**
     * Tombstone не цель. Дубль ключа экземпляра — отказ. Пустое имя — отказ.
     *
     * @return void
     */
    public function testTombstoneDuplicateKeyAndName(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('elf', 'species', []),
        ], ['gone' => true]);
        $choices = new CharacterChoices('  ', 'elf', [
            $this->ability('skill', 1, '', '', null, null, null),
            $this->ability('skill', 1, '', '', null, null, null),
            $this->ability('gone', 1, 'x', '', null, null, null),
        ], [new CharacterInventoryChoice('gone', false)], [], [new CharacterCustomRule(null, 'note', '', '', '', null)], true);
        $codes = $this->codes($this->sheets($this->createMock(IMechanics::class))->validate($slice, $choices)->getProblems());

        self::assertContains('CHARACTER_NAME', $codes);
        self::assertContains('CHARACTER_TOMBSTONE', $codes);
        self::assertContains('CHARACTER_INSTANCE_KEY', $codes);
        self::assertContains('CHARACTER_CUSTOM_RULE', $codes);
        self::assertContains('CHARACTER_RACE', $codes);
    }

    /**
     * Надетый предмет отдаёт поля брони и щита. occupy_hands в снимок не входит.
     *
     * @return void
     */
    public function testEquippedItemFields(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['abilities' => []]),
            $this->rule('mail', 'item', [
                'occupy_hands' => ['min' => 1, 'max' => 1],
                'armor' => [
                    'strength_penalty' => 1,
                    'max_agility' => ['base' => 4, 'size' => 0],
                    'characteristic_limits' => [[
                        'characteristic_code' => 'dexterity',
                        'limit' => ['type' => 'fixed', 'value' => 4],
                    ]],
                ],
                'shield' => [
                    'characteristic_limits' => [[
                        'characteristic_code' => 'strength',
                        'limit' => ['type' => 'fixed', 'value' => 2],
                    ]],
                ],
            ]),
        ], []);
        $choices = new CharacterChoices('Имя', 'human', [], [
            new CharacterInventoryChoice('mail', true),
            new CharacterInventoryChoice('mail', false),
        ], [], [], true);
        $modifiers = $this->sheets($this->createMock(IMechanics::class))->validate($slice, $choices)->getEquippedModifiers();

        self::assertCount(1, $modifiers);
        self::assertSame(1, $modifiers[0]->getStrengthPenalty());
        self::assertSame(4, $modifiers[0]->getMaxAgility());
        $limits = $modifiers[0]->getCharacteristicLimits();
        self::assertSame('dexterity', $limits[0]->getCharacteristicCode());
        self::assertSame(4, $limits[0]->getLimit());
        self::assertSame('strength', $limits[1]->getCharacteristicCode());
        self::assertSame(2, $limits[1]->getLimit());
    }

    /**
     * Поставка не purchase_surcharge 1.0.0 — problem. Нет строки каталога — нет problem.
     *
     * @return void
     */
    public function testUnsupportedHandlerIsAProblem(): void
    {
        $mechanics = $this->createMock(IMechanics::class);
        $mechanics->method('get')->willReturnCallback(static function (int $id): MechanicRecord {
            if ($id === 9) {
                throw new MechanicNotFoundException('missing');
            }

            return MechanicRecord::fromNormalized([
                'id' => $id,
                'code' => 'other',
                'name' => 'Чужое',
                'description' => '',
                'handler_version' => '9.0.0',
            ]);
        });
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['abilities' => []]),
            $this->rule('priced', 'ability', [], [[
                'mechanic_id' => 4,
                'mechanic_payload' => [
                    'type' => 'purchase_surcharge',
                    'filter' => [],
                    'free_count' => 1,
                    'surcharge' => 1,
                ],
            ], [
                'mechanic_id' => 9,
                'mechanic_payload' => [
                    'type' => 'purchase_surcharge',
                    'filter' => [],
                    'free_count' => 1,
                    'surcharge' => 1,
                ],
            ], [
                'mechanic_id' => 3,
                'mechanic_payload' => ['type' => 'other'],
            ]]),
        ], []);
        $choices = new CharacterChoices('Имя', 'human', [], [], [], [], true);
        $codes = $this->codes($this->sheets($mechanics)->validate($slice, $choices)->getProblems());

        self::assertSame(['CHARACTER_HANDLER'], $codes);
    }

    /**
     * Нет ключа active — true. Не bool — отказ input.
     *
     * @return void
     */
    public function testActiveDefaultsTrue(): void
    {
        $reader = new CharacterActiveInput();
        $missing = new CharacterProblemList();
        self::assertTrue($reader->read([], $missing));
        self::assertSame([], $missing->all());

        $bad = new CharacterProblemList();
        self::assertTrue($reader->read(['active' => 'yes'], $bad));
        self::assertSame('CHARACTER_ACTIVE', $bad->all()[0]->getCode());
        self::assertSame('input', $bad->all()[0]->getStage());

        $validation = $this->sheets($this->createMock(IMechanics::class))->validate(
            new CharacterRuleSlice(1, 1, 'world', [$this->rule('human', 'race', ['abilities' => []])], []),
            new CharacterChoices('Имя', 'human', [], [], [], [], null),
        );
        self::assertTrue($validation->isActive());
        self::assertNotContains('CHARACTER_ACTIVE', $this->codes($validation->getProblems()));
    }

    /**
     * Два экземпляра одного кода держат свои пути. Чужой grantedBy не снимает неоднозначность.
     * Повтор записи донора не удваивает денежный грант.
     *
     * @return void
     */
    public function testInstancePathsPointerAndSingleMoneyGrant(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['abilities' => []]),
            $this->rule('shaman', 'magic_path', ['includes_path_codes' => ['psionic']]),
            $this->rule('psionic', 'magic_path', []),
            $this->rule('feature', 'ability', ['grants' => [[
                'level' => 1,
                'grants' => [
                    ['type' => 'magic_study', 'scope' => 'spell', 'max_cost' => 1, 'path_code' => 'shaman'],
                    ['type' => 'money', 'fixed' => 1, 'percent' => 0, 'apply' => 'shop'],
                    ['type' => 'skill_study', 'ability_codes' => ['script'], 'max_level' => 1, 'paid_cost' => 0],
                ],
            ]]]),
            $this->rule('other', 'ability', ['grants' => [[
                'level' => 1,
                'grants' => [
                    ['type' => 'skill_study', 'ability_codes' => ['script'], 'max_level' => 1, 'paid_cost' => 0],
                ],
            ]]]),
            $this->rule('bolt', 'ability', []),
            $this->rule('script', 'ability', []),
        ], []);
        $choices = new CharacterChoices('Имя', 'human', [
            $this->ability('feature', 1, '', '', null, null, null),
            $this->ability('feature', 1, 'copy', '', null, null, null),
            $this->ability('other', 1, '', '', null, null, null),
            $this->ability('bolt', 1, 'magic-path', 'psionic', null, null, null),
            $this->ability('bolt', 1, 'magic-path', 'shaman', null, null, null),
            $this->ability('script', 1, '', '', 'missing', null, null),
        ], [], [], [], true);
        $validation = $this->sheets($this->createMock(IMechanics::class))->validate($slice, $choices);

        self::assertSame(['psionic'], $validation->getCoveredPaths()['bolt:psionic:magic-path']);
        self::assertSame(['shaman'], $validation->getCoveredPaths()['bolt:shaman:magic-path']);
        self::assertNotContains('CHARACTER_GRANT', $this->moneyCodes($validation->getProblems()));
        self::assertContains('CHARACTER_GRANT', $this->codes($validation->getProblems()));
    }

    /**
     * Коды отказов денежного гранта отдельно не выделяются: смотрим, что повтор донора не плодит отказ.
     * Чужой указатель skill_study оставляет отказ.
     *
     * @param array<int, CharacterProblem> $problems Отказы.
     *
     * @return array<int, string> Коды денежных отказов. Пусто, если текста money нет.
     */
    private function moneyCodes(array $problems): array
    {
        $codes = [];
        foreach ($problems as $problem) {
            if ($problem->getMessage() === 'Money grant is ambiguous') {
                $codes[] = $problem->getCode();
            }
        }

        return $codes;
    }

    /**
     * Валидатор из контейнера.
     *
     * @return void
     */
    public function testBootResolvesSheets(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $sheets = $application->getLocator()->get(ICharacterContainer::class)->get(ICharacterSheets::class);
        self::assertInstanceOf(CharacterSheets::class, $sheets);
    }

    /**
     * Сборщик с движком из контейнера.
     *
     * @param IMechanics $mechanics Каталог.
     * @param ICharacterRuleSlices|null $ruleSlices Срез или пустой мок.
     *
     * @return CharacterSheets Валидатор.
     */
    private function sheets(IMechanics $mechanics, ?ICharacterRuleSlices $ruleSlices = null): CharacterSheets
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $engine = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        self::assertInstanceOf(IMechanicEngine::class, $engine);

        return new CharacterSheets(
            new CharacterOsSteps($engine, $mechanics),
            $mechanics,
            $ruleSlices ?? $this->createMock(ICharacterRuleSlices::class),
        );
    }

    /**
     * Срез: раса, вид, пути, грант с пустым и непустым path_code.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', [
                'parent_race_code' => 'folk',
                'abilities' => [['ability_code' => 'resist', 'automatic' => true, 'parameters' => ['x' => ['base' => 2, 'size' => 0]]]],
            ]),
            $this->rule('folk', 'species', [
                'abilities' => [['ability_code' => 'hearing', 'automatic' => false]],
            ]),
            $this->rule('shaman', 'magic_path', ['includes_path_codes' => ['psionic']]),
            $this->rule('psionic', 'magic_path', ['includes_path_codes' => []]),
            $this->rule('feature', 'ability', ['grants' => [[
                'level' => 1,
                'grants' => [
                    ['type' => 'magic_study', 'scope' => 'spell', 'max_cost' => 1, 'path_code' => 'shaman'],
                    ['type' => 'magic_study', 'scope' => 'spell', 'max_cost' => 1, 'path_code' => ''],
                ],
            ]]]),
            $this->rule('bolt', 'ability', []),
            $this->rule('open', 'ability', []),
            $this->rule('resist', 'ability', []),
        ], []);
    }

    /**
     * Выборы примера.
     *
     * @return CharacterChoices Выборы.
     */
    private function choices(): CharacterChoices
    {
        return new CharacterChoices('Имя', 'human', [
            $this->ability('feature', 1, '', '', null, null, null),
            $this->ability('bolt', 1, 'magic-path', 'psionic', null, 'pair', 'free'),
            $this->ability('open', 1, 'magic-path', 'other', null, 'pair', 'charged'),
            $this->ability('resist', 3, '', '', null, null, null),
        ], [], [], [new CharacterCustomRule(null, 'note', 'Своё', 'Текст', 'open', null)], true);
    }

    /**
     * Выбор способности.
     *
     * @param string $code Код.
     * @param int $level Уровень.
     * @param string $domain Домен.
     * @param string $domainCode Путь.
     * @param string|null $grantedBy Донор.
     * @param string|null $pairId Пара.
     * @param string|null $role Роль.
     *
     * @return CharacterAbilityChoice Выбор.
     */
    private function ability(
        string $code,
        int $level,
        string $domain,
        string $domainCode,
        ?string $grantedBy,
        ?string $pairId,
        ?string $role,
    ): CharacterAbilityChoice {
        return new CharacterAbilityChoice($code, $level, $domain, $domainCode, $grantedBy, $pairId, $role);
    }

    /**
     * Цена вне лестницы purchased — отказ. Нулевая цена ступенью не считается.
     *
     * @return void
     */
    public function testAcceptsStoredChoices(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['characteristics' => [[
                'characteristic_code' => 'strength',
                'mode' => 'purchased',
                'base' => ['base' => 3, 'size' => 0],
                'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
            ]]]),
        ], []);
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->with(1, 1)->willReturn($slice);
        $sheets = $this->sheets($this->createMock(IMechanics::class), $slices);
        $stored = [
            'name' => 'Имя',
            'raceCode' => 'human',
            'abilities' => [],
            'inventory' => [],
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'active' => true,
        ];
        self::assertTrue($sheets->acceptsStoredChoices(1, 1, $stored));
        $stored['characteristicPurchases'] = [['characteristicCode' => 'strength', 'cost' => 9]];
        self::assertFalse($sheets->acceptsStoredChoices(1, 1, $stored));
        self::assertFalse($sheets->acceptsStoredChoices(1, 1, ['raceCode' => null]));
    }

    /**
     * Цена вне лестницы purchased — отказ. Нулевая цена ступенью не считается.
     *
     * @return void
     */
    public function testCharacteristicPurchaseMustMatchRaceLadder(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['characteristics' => [[
                'characteristic_code' => 'strength',
                'mode' => 'purchased',
                'base' => ['base' => 3, 'size' => 0],
                'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
            ]]]),
        ], []);
        $choices = new CharacterChoices('Имя', 'human', [], [], [
            new CharacterCharacteristicPurchase('strength', 0),
            new CharacterCharacteristicPurchase('strength', 2),
        ], [], true);
        $validation = $this->sheets($this->createMock(IMechanics::class))->validate($slice, $choices);
        $bought = $validation->getPurchasedCharacteristics();

        self::assertSame([], $this->codes($validation->getProblems()));
        self::assertSame('strength', $bought[0]->getCharacteristicCode());
        self::assertSame(2, $bought[0]->getCost());
        self::assertSame(4, $bought[0]->getValue()->getBase());
        self::assertSame(0, $bought[0]->getValue()->getSize());

        $miss = new CharacterChoices('Имя', 'human', [], [], [
            new CharacterCharacteristicPurchase('strength', 9),
        ], [], true);
        $codes = $this->codes($this->sheets($this->createMock(IMechanics::class))->validate($slice, $miss)->getProblems());
        self::assertSame(['CHARACTER_PURCHASE'], $codes);
    }

    /**
     * Правило среза.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Spec.
     * @param array<int, array<string, mixed>> $mechanics Механики.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec, array $mechanics = []): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(10, 1, $code, true, $type, $code, '', $spec, [], $mechanics, 'needs_work', '', DateTime::now()),
            [],
        );
    }

    /**
     * Коды отказов.
     *
     * @param array<int, CharacterProblem> $problems Отказы.
     *
     * @return array<int, string> Коды.
     */
    private function codes(array $problems): array
    {
        $codes = [];
        foreach ($problems as $problem) {
            $codes[] = $problem->getCode();
        }

        return $codes;
    }
}
