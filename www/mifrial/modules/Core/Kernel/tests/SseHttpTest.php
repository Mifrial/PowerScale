<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use PHPUnit\Framework\TestCase;

final class SseHttpTest extends TestCase
{
    /**
     * prepareHttp кладёт cookie в контекст без CSRF и без JSON-exit.
     *
     * @return void
     */
    public function testPrepareHttpBindsCookiesWithoutCsrf(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn(['csrf-token' => 'abc']);
        $application->prepareHttp($httpRequest);
        $requestContext = $application->getLocator()
            ->get(IKernelContainer::class)
            ->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        self::assertSame('abc', $requestContext->incomingCookie('csrf-token'));
    }

    /**
     * Событие и комментарий — формат SSE, без Chat.
     *
     * @return void
     */
    public function testSseEmitterWritesEventAndComment(): void
    {
        $chunks = '';
        $emitter = new SseEmitter(
            static function (string $chunk) use (&$chunks): void {
                $chunks .= $chunk;
            },
            false,
        );
        self::assertFalse($emitter->hasStarted());
        $emitter->start();
        $emitter->writeEvent('sync', ['now' => 1, 'afterId' => 0]);
        $emitter->writeComment('ping');

        self::assertTrue($emitter->hasStarted());
        self::assertSame(
            "event: sync\ndata: {\"now\":1,\"afterId\":0}\n\n: ping\n\n",
            $chunks,
        );
    }

    /**
     * Сбой после start() — лог, не JSON-конверт поверх потока.
     *
     * @return void
     */
    public function testEmitHttpErrorAfterStreamReturns(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn([]);
        $httpRequest->method('getQueryValue')->willReturn(null);
        $application->prepareHttp($httpRequest);
        ob_start();
        $application->emitHttpError(
            $httpRequest,
            new KernelException('INTERNAL', 'tick failed'),
            true,
        );
        $output = ob_get_clean();

        self::assertSame('', $output);
    }
}
