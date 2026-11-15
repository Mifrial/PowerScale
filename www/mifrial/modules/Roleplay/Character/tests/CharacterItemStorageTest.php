<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterInventoryChoice;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Service\CharacterOsSteps;
use Mifrial\Roleplay\Character\Service\CharacterSheets;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * План M10: Объём предмета-хранилища — ступень покупки и предел от мощи.
 */
final class CharacterItemStorageTest extends TestCase
{
    /**
     * Объём ступени 20 при мощи 4 проходит.
     *
     * @return void
     */
    public function testStepVolumeIsAccepted(): void
    {
        self::assertSame([], $this->codes($this->crystal([4, 0], 20)));
        self::assertSame([], $this->codes($this->crystal([4, 0], 30)));
        self::assertSame([], $this->codes($this->crystal([5, 1], 20)));
    }

    /**
     * Объём ниже удвоенной мощи — отказ.
     *
     * @return void
     */
    public function testVolumeBelowPowerMinimumIsRejected(): void
    {
        self::assertSame(['CHARACTER_ITEM_VOLUME'], $this->codes($this->crystal([5, 2], 30)));
        self::assertSame(['CHARACTER_ITEM_VOLUME'], $this->codes($this->crystal([5, 2], 20)));
    }

    /**
     * Объём вне ступени — отказ.
     *
     * @return void
     */
    public function testVolumeOffTheStepIsRejected(): void
    {
        self::assertSame(['CHARACTER_ITEM_VOLUME'], $this->codes($this->crystal([3, 0], 25)));
    }

    /**
     * Нет мощи экземпляра — отказ.
     *
     * @return void
     */
    public function testMissingPowerIsRejected(): void
    {
        $item = new CharacterInventoryChoice('magic-crystal', false, 1, [], [], null, ['volume' => 20]);

        self::assertSame(['CHARACTER_ITEM_VOLUME'], $this->codes($item));
    }

    /**
     * Нет Объёма в payload — отказ.
     *
     * @return void
     */
    public function testMissingVolumeIsRejected(): void
    {
        $item = new CharacterInventoryChoice(
            'magic-crystal',
            false,
            1,
            [],
            ['max_power' => new DimensionalNumber(4, 0)],
            null,
            [],
        );

        self::assertSame(['CHARACTER_ITEM_VOLUME'], $this->codes($item));
    }

    /**
     * Предмет без блока storage не проверяется.
     *
     * @return void
     */
    public function testPlainItemIsIgnored(): void
    {
        $item = new CharacterInventoryChoice('sword', true);

        self::assertSame([], $this->codes($item));
    }

    /**
     * Пустой кристалл нельзя сохранить: он не имеет смысла без записей.
     *
     * @return void
     */
    public function testEmptyRecordedSpellsAreRejected(): void
    {
        self::assertSame(['CHARACTER_ITEM_RECORDED'], $this->codes($this->crystal([3, 0], 20, [])));
    }

    /**
     * Runtime-операции не блокируются старым пустым кристаллом.
     *
     * @return void
     */
    public function testEmptyRecordedSpellsAreToleratedInRuntime(): void
    {
        self::assertSame([], $this->codes($this->crystal([3, 0], 20, []), false));
    }

    /**
     * Кристалл принимает только заклинания с разрешённым признаком.
     *
     * @return void
     */
    public function testForeignSpellKeywordIsRejected(): void
    {
        $item = $this->crystal([3, 0], 20, [
            ['ruleCode' => 'frost'],
        ]);

        self::assertSame(['CHARACTER_ITEM_RECORDED'], $this->codes($item));
    }

    /**
     * Запись влезает в Объём и оставляет ману.
     *
     * @return void
     */
    public function testRecordedSpellFits(): void
    {
        $item = $this->crystal([3, 0], 20, [
            ['ruleCode' => 'spark'],
        ]);

        self::assertSame([], $this->codes($item));
    }

    /**
     * Записи не влезают в Объём.
     *
     * @return void
     */
    public function testRecordedSpellExceedsVolume(): void
    {
        $item = $this->crystal([3, 0], 20, [
            ['ruleCode' => 'big-spell'],
        ]);

        self::assertSame(['CHARACTER_ITEM_VOLUME'], $this->codes($item));
    }

    /**
     * Записанное улучшение занимает Объём отдельно.
     *
     * @return void
     */
    public function testRecordedUpgradeTakesVolume(): void
    {
        $item = $this->crystal([3, 0], 20, [
            ['ruleCode' => 'spark', 'upgrades' => ['careful']],
        ]);

        self::assertSame(['CHARACTER_ITEM_VOLUME'], $this->codes($item));
    }

    /**
     * Нет карточки заклинания — отказ записи.
     *
     * @return void
     */
    public function testMissingRecordedSpellIsRejected(): void
    {
        $item = $this->crystal([3, 0], 20, [
            ['ruleCode' => 'missing-spell'],
        ]);

        self::assertSame(['CHARACTER_ITEM_RECORDED'], $this->codes($item));
    }

    /**
     * Повтор записи — отказ.
     *
     * @return void
     */
    public function testDuplicateRecordedSpellIsRejected(): void
    {
        $item = $this->crystal([3, 0], 20, [
            ['ruleCode' => 'spark'],
            ['ruleCode' => 'spark'],
        ]);

        self::assertSame(['CHARACTER_ITEM_RECORDED'], $this->codes($item));
    }

    /**
     * Улучшение без карточки-улучшения — отказ.
     *
     * @return void
     */
    public function testForeignUpgradeIsRejected(): void
    {
        $item = $this->crystal([3, 0], 20, [
            ['ruleCode' => 'spark', 'upgrades' => ['sword']],
        ]);

        self::assertSame(['CHARACTER_ITEM_RECORDED'], $this->codes($item));
    }

    /**
     * Строка кристалла.
     *
     * @param array{0: int, 1: int} $power База и размер мощи.
     * @param int $volume Объём.
     *
     * @return CharacterInventoryChoice Строка.
     */
    private function crystal(array $power, int $volume, array $recorded = [['ruleCode' => 'spark']]): CharacterInventoryChoice
    {
        return new CharacterInventoryChoice(
            'magic-crystal',
            false,
            1,
            [],
            ['max_power' => new DimensionalNumber($power[0], $power[1])],
            null,
            ['volume' => $volume, 'recorded_spells' => $recorded],
        );
    }

    /**
     * Отказы валидатора по одной строке.
     *
     * @param CharacterInventoryChoice $item Строка.
     * @param bool $requireRecorded Требовать непустые хранилища.
     *
     * @return array<int, string> Коды.
     */
    private function codes(CharacterInventoryChoice $item, bool $requireRecorded = true): array
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $mechanics = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $engine = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        self::assertInstanceOf(IMechanicEngine::class, $engine);
        $ruleSlices = $this->createMock(ICharacterRuleSlices::class);
        $sheets = new CharacterSheets(new CharacterOsSteps($engine, $mechanics), $mechanics, $ruleSlices);
        $choices = new CharacterChoices('Имя', 'human', [], [$item], [], [], true);
        $validation = $sheets->validate($this->slice(), $choices, $requireRecorded);

        $codes = [];
        foreach ($validation->getProblems() as $problem) {
            $codes[] = $problem->getCode();
        }

        return $codes;
    }

    /**
     * Срез с кристаллом и мечом.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['parent_race_code' => null, 'abilities' => []]),
            $this->rule('magic-crystal', 'item', [
                'storage' => [
                    'power_parameter' => 'max_power',
                    'volume_parameter' => 'volume',
                    'recorded_parameter' => 'recorded_spells',
                    'volume_base' => 20,
                    'volume_increment' => 10,
                    'min_volume_power_delta' => 3,
                    'recorded_keywords' => ['electromancy'],
                ],
            ]),
            $this->rule('sword', 'item', []),
            $this->rule('spark', 'ability', $this->spell([1]), ['electromancy']),
            $this->rule('big-spell', 'ability', $this->spell([3]), ['electromancy']),
            $this->rule('frost', 'ability', $this->spell([1])),
            $this->rule('careful', 'ability', [
                'type' => 'skill',
                'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                'spell_upgrade' => ['action_point_delta' => 0],
            ]),
        ], []);
    }

    /**
     * Карточка заклинания с ценой.
     *
     * @param array<int, int> $levels Цены уровней.
     *
     * @return array<string, mixed> Spec.
     */
    private function spell(array $levels): array
    {
        return [
            'type' => 'spell',
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => $levels]],
            'spec' => [],
            'spell' => [
                'power' => ['base' => 3, 'size' => 0],
                'control' => ['base' => 3, 'size' => 0],
                'targeting' => 'none',
                'duration' => ['type' => 'instant'],
            ],
            'parameters' => [],
            'hit_resolution' => ['type' => 'none'],
            'action_components' => [],
        ];
    }

    /**
     * Живое правило.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Spec.
     * @param array<int, string> $keywords Признаки.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec, array $keywords = []): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(10, 1, $code, true, $type, $code, '', $spec, [], [], 'needs_work', '', DateTime::now()),
            $keywords,
        );
    }
}
