<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellCharge;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellCharge;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет операции заряда на источнике.
 */
final class GameSpellChargeTest extends TestCase
{
    /**
     * Выдача заменяет прежний заряд и не плодит строки.
     *
     * @return void
     */
    public function testGrantReplacesBoundRows(): void
    {
        $charge = $this->charge();
        $sheet = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
            ['id' => 4, 'stateRuleCode' => 'electrocharge', 'value' => 2, 'boundSourceKey' => 'characteristic:magic-power'],
            ['id' => 5, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'inventory:9'],
        ]];

        self::assertSame(
            [
                ['kind' => 'patchState', 'id' => 3, 'value' => 1],
                ['kind' => 'removeState', 'id' => 4],
            ],
            (new GameSpellCharge())->grant($sheet, $charge, 'characteristic:magic-power', 3, true),
        );
        self::assertSame(
            1,
            (new GameSpellCharge())->value($sheet, 'electrocharge', 'inventory:9'),
        );
    }

    /**
     * Пустой лист получает новую строку с привязкой к источнику.
     *
     * @return void
     */
    public function testGrantAddsRowToEmptySheet(): void
    {
        self::assertSame(
            [[
                'kind' => 'putState',
                'stateRuleCode' => 'electrocharge',
                'value' => 1,
                'boundSourceKey' => 'characteristic:magic-power',
            ]],
            (new GameSpellCharge())->grant([], $this->charge(), 'characteristic:magic-power', 2, true),
        );
    }

    /**
     * Обновление поддержания прибавляет заряд до капа.
     *
     * @return void
     */
    public function testGrantAddsUpToCap(): void
    {
        $sheet = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
        ]];

        self::assertSame(
            [['kind' => 'patchState', 'id' => 3, 'value' => 2]],
            (new GameSpellCharge())->grant($sheet, $this->charge(), 'characteristic:magic-power', 2, false),
        );
        self::assertSame(
            [],
            (new GameSpellCharge())->grant($sheet, $this->charge(), 'characteristic:magic-power', 1, false),
        );
    }

    /**
     * Трата уменьшает заряд, а ноль снимает строку.
     *
     * @return void
     */
    public function testSpendDecrementsAndRemovesZero(): void
    {
        $service = new GameSpellCharge();
        $two = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 2, 'boundSourceKey' => 'characteristic:magic-power'],
        ]];
        $one = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
        ]];

        self::assertSame(
            [['kind' => 'patchState', 'id' => 3, 'value' => 1]],
            $service->spend($two, $this->charge(), 'characteristic:magic-power'),
        );
        self::assertSame(
            [['kind' => 'removeState', 'id' => 3]],
            $service->spend($one, $this->charge(), 'characteristic:magic-power'),
        );
    }

    /**
     * Пустой заряд не тратится.
     *
     * @return void
     */
    public function testSpendRejectsEmptyCharge(): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameSpellCharge())->spend([], $this->charge(), 'characteristic:magic-power');
    }

    /**
     * Снятие убирает только строки этого источника.
     *
     * @return void
     */
    public function testRemoveOnlyBoundRows(): void
    {
        $sheet = ['states' => [
            ['id' => 3, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'characteristic:magic-power'],
            ['id' => 5, 'stateRuleCode' => 'electrocharge', 'value' => 1, 'boundSourceKey' => 'inventory:9'],
        ]];

        self::assertSame(
            [['kind' => 'removeState', 'id' => 3]],
            (new GameSpellCharge())->remove($sheet, 'electrocharge', 'characteristic:magic-power'),
        );
    }

    /**
     * Карточка заряда контента.
     *
     * @return SpellCharge Заряд.
     */
    private function charge(): SpellCharge
    {
        return new SpellCharge('electrocharge', 1, 1, 2, 1, 'electromancy', ['sustained'], 'turn_ap');
    }
}
