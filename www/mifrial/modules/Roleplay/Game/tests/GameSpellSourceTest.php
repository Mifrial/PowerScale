<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellSource;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет принадлежность и доступность источника каста.
 */
final class GameSpellSourceTest extends TestCase
{
    /**
     * Предмет источника: при касте достаточно наличия, при поддержании — надетости.
     *
     * @return void
     */
    public function testInventorySource(): void
    {
        $source = $this->source();
        $path = $this->path();
        $slice = $this->slice();
        $choices = ['inventory' => [['id' => 9, 'equipped' => false]]];

        $source->assertOwned(['kind' => 'item', 'inventoryId' => 9], $choices, [], $path, $slice);
        self::assertFalse($source->isAvailable('inventory:9', $choices, [], $path, $slice));

        $choices['inventory'][0]['equipped'] = true;
        self::assertTrue($source->isAvailable('inventory:9', $choices, [], $path, $slice));
        self::assertFalse($source->isAvailable('inventory:10', $choices, [], $path, $slice));
    }

    /**
     * Характеристика источника должна совпадать с характеристикой мощи пути.
     *
     * @return void
     */
    public function testCharacteristicSource(): void
    {
        $source = $this->source();
        $path = $this->path();
        $slice = $this->slice();
        $sheet = ['characteristics' => [
            ['characteristicCode' => 'magic-power', 'value' => ['base' => 4, 'size' => 0]],
        ]];

        self::assertTrue($source->isAvailable('characteristic:magic-power', [], $sheet, $path, $slice));
        self::assertFalse($source->isAvailable('characteristic:magic-control', [], $sheet, $path, $slice));

        $source->assertOwned(
            ['kind' => 'characteristic', 'code' => 'magic-power'],
            [],
            $sheet,
            $path,
            $slice,
        );
    }

    /**
     * Чужой источник при касте отклоняется.
     *
     * @return void
     */
    public function testRejectsForeignSource(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->source()->assertOwned(
            ['kind' => 'characteristic', 'code' => 'magic-control'],
            [],
            [],
            $this->path(),
            $this->slice(),
        );
    }

    /**
     * Источник с проверкой грантов.
     *
     * @return GameSpellSource Источник.
     */
    private function source(): GameSpellSource
    {
        return new GameSpellSource(new CharacterAbilityGrantReader(
            new CharacterDonorGrants(),
            new CharacterRaceAbilityCodes(),
        ));
    }

    /**
     * Путь с характеристикой мощи.
     *
     * @return MagicPathSpec Путь.
     */
    private function path(): MagicPathSpec
    {
        return new MagicPathSpec(null, 'check-spell-cast', 'magic-power', 'magic-control', null, []);
    }

    /**
     * Пустой срез.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [], []);
    }
}
