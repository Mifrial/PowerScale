<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Dto\OutgoingCookie;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Http\RequestContext;
use Mifrial\Core\Kernel\Http\ResponseEmitter;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Service\RuntimeConfig;
use PHPUnit\Framework\TestCase;

final class RequestContextTest extends TestCase
{
    /**
     * Копирует строковые cookie и отдаёт очередь.
     *
     * @return void
     */
    public function testBindIncomingAndQueue(): void
    {
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn([
            'mifrial-session' => 'raw-token',
            'empty' => '',
            'num' => 1,
        ]);
        $requestContext = new RequestContext();
        $requestContext->bindIncoming($httpRequest);
        $requestContext->queueCookie(new OutgoingCookie(
            'csrf-token',
            'abc',
            false,
            false,
            3600,
        ));

        self::assertSame('raw-token', $requestContext->incomingCookie('mifrial-session'));
        self::assertNull($requestContext->incomingCookie('empty'));
        $queuedCookies = $requestContext->takeQueuedCookies();
        self::assertCount(1, $queuedCookies);
        self::assertSame([], $requestContext->takeQueuedCookies());
        self::assertStringContainsString('csrf-token=abc', $queuedCookies[0]->headerLine());
        self::assertStringContainsString('Max-Age=3600', $queuedCookies[0]->headerLine());
        self::assertStringNotContainsString('HttpOnly', $queuedCookies[0]->headerLine());
    }

    /**
     * reset очищает входящие и очередь.
     *
     * @return void
     */
    public function testResetClearsCookies(): void
    {
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn(['mifrial-session' => 'x']);
        $requestContext = new RequestContext();
        $requestContext->bindIncoming($httpRequest);
        $requestContext->queueCookie(new OutgoingCookie('a', 'b', true, false, 1));
        $requestContext->reset();

        self::assertNull($requestContext->incomingCookie('mifrial-session'));
        self::assertSame([], $requestContext->takeQueuedCookies());
        self::assertNull($requestContext->getActor());
    }

    /**
     * setActor и reset/bindIncoming сбрасывают актора.
     *
     * @return void
     */
    public function testActorResetAndBindIncoming(): void
    {
        $requestContext = new RequestContext();
        $requestActor = new RequestActor(3, ['user.view'], false);
        $requestContext->setActor($requestActor);
        self::assertSame(3, $requestContext->getActor()?->getUserId());
        $requestContext->reset();
        self::assertNull($requestContext->getActor());
        $requestContext->setActor($requestActor);
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn([]);
        $requestContext->bindIncoming($httpRequest);
        self::assertNull($requestContext->getActor());
    }

    /**
     * Emitter забирает очередь в строки Set-Cookie.
     *
     * @return void
     */
    public function testEmitterQueuedCookieHeaders(): void
    {
        $requestContext = new RequestContext();
        $requestContext->queueCookie(new OutgoingCookie(
            'mifrial-session',
            'tok',
            true,
            true,
            0,
        ));
        $emitter = new ResponseEmitter(requestContext: $requestContext);
        $headers = $emitter->queuedCookieHeaders();

        self::assertCount(1, $headers);
        self::assertStringContainsString('mifrial-session=tok', $headers[0]);
        self::assertStringContainsString('HttpOnly', $headers[0]);
        self::assertStringContainsString('Secure', $headers[0]);
        self::assertSame('{"success":true,"data":null}', $emitter->encodeJson(ActionResponse::ok(null)));
        self::assertSame([], $emitter->queuedCookieHeaders());
    }

    /**
     * dispatch не подставляет cookie; extra-порт Kernel доступен.
     *
     * @return void
     */
    public function testDispatchLeavesIncomingCookiesEmpty(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $application->dispatch('mifrial.ping', null);
        $kernelContainer = $application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);

        self::assertInstanceOf(IRequestContext::class, $requestContext);
        self::assertNull($requestContext->incomingCookie('mifrial-session'));
        self::assertSame([], $requestContext->takeQueuedCookies());
    }

    /**
     * section читает ключ local или null.
     *
     * @return void
     */
    public function testRuntimeConfigSection(): void
    {
        $runtimeConfig = RuntimeConfig::fromLocal([
            'debug' => false,
            'auth' => ['cookie_secure' => false],
        ]);

        self::assertSame(['cookie_secure' => false], $runtimeConfig->section('auth'));
        self::assertNull($runtimeConfig->section('missing'));
        self::assertInstanceOf(IRuntimeConfig::class, $runtimeConfig);
    }
}
