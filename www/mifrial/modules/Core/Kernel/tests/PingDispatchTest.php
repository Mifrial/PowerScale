<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Interface\Service\IKernelContainer;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use PHPUnit\Framework\TestCase;

final class PingDispatchTest extends TestCase
{
    /**
     * Проверяет успешный ответ ping-действия.
     *
     * @return void
     */
    public function testMifrialPingReturnsOk(): void
    {
        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $response = $app->dispatch('mifrial.ping', null);
        $payload = $response->toArray();

        self::assertTrue($payload['success']);
        self::assertSame(['ok' => true], $payload['data']);
        self::assertArrayNotHasKey('error', $payload);
        self::assertTrue($app->getLocator()->has(IKernelContainer::class));
    }

    /**
     * Проверяет ошибку для неизвестного действия.
     *
     * @return void
     */
    public function testUnknownActionFails(): void
    {
        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $response = $app->dispatch('no.such', null);
        $payload = $response->toArray();

        self::assertFalse($payload['success']);
        self::assertNull($payload['data']);
        self::assertSame('UNKNOWN_ACTION', $payload['error']['code']);
    }

    /**
     * Проверяет, что лишние поля ping отклоняются.
     *
     * @return void
     */
    public function testPingRejectsUnknownPayloadField(): void
    {
        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $response = $app->dispatch('mifrial.ping', ['unexpected' => true]);
        $payload = $response->toArray();

        self::assertFalse($payload['success']);
        self::assertSame('INVALID_PARAMS', $payload['error']['code']);
    }
}
