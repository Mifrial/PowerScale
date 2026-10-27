<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Service\GameStrikeChat;
use Mifrial\Roleplay\Game\Service\GameStrikeRules;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Текст условий дальнего боя для чата.
 */
final class GameStrikeChatTest extends TestCase
{
    /**
     * Дистанция, укрытие, полоса и итоговая сложность попадают в строку.
     *
     * @return void
     */
    public function testRangedConditionsText(): void
    {
        $chat = new GameStrikeChat(
            (new ReflectionClass(GameNpcRepository::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(GameStrikeRules::class))->newInstanceWithoutConstructor(),
        );

        self::assertSame(
            'Дистанция 11 ипари (дальнобойность 10, +1 размер), укрытие 1↓, защита 1↓ → сложность 3.',
            $chat->getRangedConditionsText([
                'ranged_hit' => [
                    'distance_value' => ['base' => 11, 'size' => 0],
                    'falloff_value' => ['base' => 10, 'size' => 0],
                    'cover_value' => ['base' => 1, 'size' => -1],
                    'defense_value' => ['base' => 1, 'size' => -1],
                    'difficulty' => ['base' => 3, 'size' => 0],
                    'range_size' => 2,
                ],
            ]),
        );
    }
}
