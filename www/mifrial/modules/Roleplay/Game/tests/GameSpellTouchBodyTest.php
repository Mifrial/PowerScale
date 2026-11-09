<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Service\GameSpellTouchBody;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет разбор блока spellCast касания.
 */
final class GameSpellTouchBodyTest extends TestCase
{
    /**
     * Улучшения касания нормализуются.
     *
     * @return void
     */
    public function testNormalizesUpgrades(): void
    {
        $raw = $this->raw();
        $raw['upgrades'] = [['code' => 'piercing', 'value' => 2]];

        $parsed = (new GameSpellTouchBody())->parse($raw);

        self::assertNotNull($parsed);
        self::assertSame([['code' => 'piercing', 'value' => 2]], $parsed['upgrades']);
    }

    /**
     * Без поля улучшений — пустой список.
     *
     * @return void
     */
    public function testEmptyUpgradesWhenAbsent(): void
    {
        $parsed = (new GameSpellTouchBody())->parse($this->raw());

        self::assertNotNull($parsed);
        self::assertSame([], $parsed['upgrades']);
    }

    /**
     * Ошибочная форма улучшений отклоняется.
     *
     * @return void
     */
    public function testRejectsInvalidUpgrades(): void
    {
        $raw = $this->raw();
        $raw['upgrades'] = ['piercing'];

        $this->expectException(ActionException::class);
        (new GameSpellTouchBody())->parse($raw);
    }

    /**
     * Минимальный блок касания.
     *
     * @return array<string, mixed> Блок.
     */
    private function raw(): array
    {
        return [
            'spellCode' => 'shock',
            'pathCode' => 'arcanist',
            'parameters' => [],
            'usedPower' => [
                'source' => ['kind' => 'characteristic', 'code' => 'power'],
                'value' => ['base' => 1, 'size' => 0],
            ],
        ];
    }
}
