<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Chat\Dto\ChatHostTypes;
use Mifrial\Messages\Chat\Dto\ChatRecord;
use PHPUnit\Framework\TestCase;

final class ChatHostTypesTest extends TestCase
{
    /**
     * type game не host; private и group — да.
     *
     * @return void
     */
    public function testDropsGameType(): void
    {
        $createdAt = DateTime::fromUnix(1);
        $privateChat = ChatRecord::fromNormalized([
            'id' => 1,
            'type' => 'private',
            'name' => '',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $gameChat = ChatRecord::fromNormalized([
            'id' => 2,
            'type' => 'game',
            'name' => 'Scene',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $groupChat = ChatRecord::fromNormalized([
            'id' => 3,
            'type' => 'group',
            'name' => 'Party',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        self::assertFalse(ChatHostTypes::isHost('game'));
        self::assertSame([$privateChat, $groupChat], ChatHostTypes::hostChats([$privateChat, $gameChat, $groupChat]));
        self::assertSame([1, 3], ChatHostTypes::hostIds([1, 2, 3], [$privateChat, $gameChat, $groupChat]));
    }
}
