<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Messages\Chat\Dto\ChatHostTypes;
use PHPUnit\Framework\TestCase;

final class ChatHostTypesTest extends TestCase
{
    /**
     * private и group — зарезервированные типы; game — нет.
     *
     * @return void
     */
    public function testReservedTypes(): void
    {
        self::assertTrue(ChatHostTypes::isHost('private'));
        self::assertTrue(ChatHostTypes::isHost('group'));
        self::assertFalse(ChatHostTypes::isHost('game'));
    }
}
