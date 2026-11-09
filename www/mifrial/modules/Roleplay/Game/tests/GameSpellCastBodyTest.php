<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\Action\CastGameSpellInput;
use Mifrial\Roleplay\Game\Service\GameSpellCastBody;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет разбор тела game.castSpell.
 */
final class GameSpellCastBodyTest extends TestCase
{
    /**
     * Корректный запрос нормализуется.
     *
     * @return void
     */
    public function testNormalizesValidBody(): void
    {
        $body = (new GameSpellCastBody())->parse($this->input());

        self::assertSame(['type' => 'character', 'id' => 3], $body['actor']);
        self::assertSame(['type' => 'npc', 'id' => 7], $body['target']);
        self::assertSame(['x' => ['base' => 4, 'size' => 0], 'bonus' => 2], $body['parameters']);
        self::assertSame(
            [
                'source' => ['kind' => 'item', 'inventoryId' => 9],
                'value' => ['base' => 3, 'size' => 0],
            ],
            $body['usedPower'],
        );
        self::assertSame(1, $body['distanceIpari']);
        self::assertSame(2, $body['expectedTargetVersion']);
    }

    /**
     * Список вместо карты параметров отклоняется.
     *
     * @return void
     */
    public function testRejectsListParameters(): void
    {
        $this->expectException(ActionException::class);
        (new GameSpellCastBody())->parse($this->input(parameters: [4]));
    }

    /**
     * Цель без версии листа отклоняется.
     *
     * @return void
     */
    public function testRejectsTargetWithoutVersion(): void
    {
        $this->expectException(ActionException::class);
        (new GameSpellCastBody())->parse($this->input(expectedTargetVersion: null));
    }

    /**
     * Неизвестный вид источника мощи отклоняется.
     *
     * @return void
     */
    public function testRejectsUnknownPowerSource(): void
    {
        $this->expectException(ActionException::class);
        (new GameSpellCastBody())->parse($this->input(usedPower: [
            'source' => ['kind' => 'source'],
            'value' => ['base' => 3, 'size' => 0],
        ]));
    }

    /**
     * Отрицательная дистанция отклоняется.
     *
     * @return void
     */
    public function testRejectsNegativeDistance(): void
    {
        $this->expectException(ActionException::class);
        (new GameSpellCastBody())->parse($this->input(distanceIpari: -1));
    }

    /**
     * Список улучшений нормализуется.
     *
     * @return void
     */
    public function testNormalizesUpgrades(): void
    {
        $body = (new GameSpellCastBody())->parse($this->input(upgrades: [
            ['code' => 'first'],
            ['code' => 'second', 'value' => 2],
        ]));

        self::assertSame([
            ['code' => 'first', 'value' => null],
            ['code' => 'second', 'value' => 2],
        ], $body['upgrades']);
    }

    /**
     * Ошибочная форма улучшений отклоняется.
     *
     * @return void
     */
    public function testRejectsInvalidUpgrades(): void
    {
        $this->expectException(ActionException::class);
        (new GameSpellCastBody())->parse($this->input(upgrades: [['code' => 'first', 'value' => -1]]));
    }

    /**
     * Вход для теста.
     *
     * @param array<mixed>|null $parameters Параметры.
     * @param array<mixed>|null $usedPower Мощь.
     * @param array<mixed>|null $target Цель.
     * @param int|null $expectedTargetVersion Версия цели.
     * @param int $distanceIpari Дистанция.
     * @param array<mixed> $upgrades Улучшения.
     *
     * @return CastGameSpellInput Вход.
     */
    private function input(
        ?array $parameters = null,
        ?array $usedPower = null,
        ?array $target = null,
        ?int $expectedTargetVersion = 2,
        int $distanceIpari = 1,
        array $upgrades = [],
    ): CastGameSpellInput {
        return new CastGameSpellInput(
            1,
            5,
            'cast-1',
            1,
            4,
            ['type' => 'character', 'id' => 3],
            'lightning-strike',
            'arcanist',
            $parameters ?? ['x' => ['base' => 4, 'size' => 0], 'bonus' => 2],
            $usedPower ?? [
                'source' => ['kind' => 'item', 'inventoryId' => 9],
                'value' => ['base' => 3, 'size' => 0],
            ],
            $expectedTargetVersion,
            $target ?? ['type' => 'npc', 'id' => 7],
            $distanceIpari,
            $upgrades,
        );
    }
}
