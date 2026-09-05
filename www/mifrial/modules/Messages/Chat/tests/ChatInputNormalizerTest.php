<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Service\ChatInputNormalizer;
use PHPUnit\Framework\TestCase;

final class ChatInputNormalizerTest extends TestCase
{
    /**
     * Пустое имя группы.
     *
     * @return void
     */
    public function testEmptyGroupNameIsInvalid(): void
    {
        try {
            (new ChatInputNormalizer())->newGroupChat('   ', 1, []);
            self::fail('empty name must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустой send; вложение без type; лишний ключ отбрасывается.
     *
     * @return void
     */
    public function testSendNormalization(): void
    {
        $normalizer = new ChatInputNormalizer();
        try {
            $normalizer->normalizeSend('  ', []);
            self::fail('empty send must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->normalizeSend('hi', [['payload' => []]]);
            self::fail('missing type must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        $normalized = $normalizer->normalizeSend('', [
            ['type' => ' roll ', 'payload' => ['n' => 1], 'extra' => true],
        ]);
        self::assertSame('', $normalized['content']);
        self::assertSame([['type' => 'roll', 'payload' => ['n' => 1]]], $normalized['attachments']);
    }

    /**
     * JSON visibility → all/users; forRole и смесь — INVALID.
     *
     * @return void
     */
    public function testAudienceNormalization(): void
    {
        $normalizer = new ChatInputNormalizer();
        $members = [1, 2];
        self::assertSame('all', $normalizer->normalizeAudience(null, $members)->getKind());
        self::assertSame('all', $normalizer->normalizeAudience([], $members)->getKind());
        self::assertSame('all', $normalizer->normalizeAudience(['all' => true], $members)->getKind());
        $users = $normalizer->normalizeAudience(['all' => false, 'forUsers' => [2, 2]], $members);
        self::assertSame('users', $users->getKind());
        self::assertSame([2], $users->getUserIds());
        try {
            $normalizer->normalizeAudience([1], $members);
            self::fail('json list must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->normalizeAudience(['all' => false, 'forRole' => 'gm'], $members);
            self::fail('forRole must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->normalizeAudience(['all' => true, 'forUsers' => []], $members);
            self::fail('all+forUsers must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->normalizeAudience(['all' => false, 'forUsers' => [3]], $members);
            self::fail('stranger must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }
    }
}
