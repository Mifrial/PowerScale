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
            'Дистанция 11 ипари, укрытие 1↓; +2 размера (полоса 3), сложность 4↓.',
            $chat->getRangedConditionsText([
                'ranged_hit' => [
                    'distance_value' => ['base' => 11, 'size' => 0],
                    'cover_value' => ['base' => 1, 'size' => -1],
                    'difficulty' => ['base' => 4, 'size' => -1],
                    'range_size' => 3,
                ],
            ]),
        );
    }
}
