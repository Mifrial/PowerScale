<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Service\ChatTypeRegistry;
use PHPUnit\Framework\TestCase;

final class ChatTypeRegistryTest extends TestCase
{
    /**
     * Повтор той же строки идемпотентен. Пусто и host — нет.
     *
     * @return void
     */
    public function testRegisterOpaqueType(): void
    {
        $registry = new ChatTypeRegistry();
        $registry->register('  donor_probe  ');
        $registry->register('donor_probe');
        self::assertTrue($registry->isRegistered('donor_probe'));
        self::assertTrue($registry->isRegistered(' donor_probe '));
        self::assertFalse($registry->isRegistered('other_probe'));
        foreach (['', '   ', 'private', 'group'] as $type) {
            try {
                $registry->register($type);
                self::fail('type must be rejected: ' . $type);
            } catch (ChatInvalidException $exception) {
                self::assertSame('CHAT_INVALID', $exception->getErrorCode());
            }
        }
    }
}
