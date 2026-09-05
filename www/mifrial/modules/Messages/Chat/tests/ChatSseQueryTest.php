<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Messages\Chat\Dto\ChatSseQuery;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use PHPUnit\Framework\TestCase;

final class ChatSseQueryTest extends TestCase
{
    /**
     * Нет since / пустая строка — live; since=0 — эпоха.
     *
     * @return void
     */
    public function testLiveAndEpoch(): void
    {
        $live = ChatSseQuery::fromRequest($this->query([]));
        self::assertTrue($live->isLive);
        self::assertSame(0, $live->afterId);

        $empty = ChatSseQuery::fromRequest($this->query(['since' => '']));
        self::assertTrue($empty->isLive);

        $epoch = ChatSseQuery::fromRequest($this->query(['since' => '0']));
        self::assertFalse($epoch->isLive);
        self::assertSame(0, $epoch->sinceUnix);
        self::assertSame(0, $epoch->afterId);
    }

    /**
     * Пара unix + afterId; ISO и мусор — CHAT_INVALID.
     *
     * @return void
     */
    public function testPairAndInvalid(): void
    {
        $query = ChatSseQuery::fromRequest($this->query(['since' => '1700000000', 'afterId' => '9']));
        self::assertFalse($query->isLive);
        self::assertSame(1700000000, $query->sinceUnix);
        self::assertSame(9, $query->afterId);

        try {
            ChatSseQuery::fromRequest($this->query(['since' => '2026-09-04T00:00:00Z']));
            self::fail('ISO must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            ChatSseQuery::fromRequest($this->query(['since' => '1', 'afterId' => '-1']));
            self::fail('negative afterId must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Снимок query.
     *
     * @param array<string, string> $query Параметры.
     *
     * @return IHttpRequest Снимок.
     */
    private function query(array $query): IHttpRequest
    {
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getQueryValue')->willReturnCallback(
            static function (string $name) use ($query): mixed {
                return $query[$name] ?? null;
            },
        );

        return $httpRequest;
    }
}
