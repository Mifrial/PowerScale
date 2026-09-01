<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\Kernel\Interface\Service\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Service\Dispatcher;
use Mifrial\Core\Kernel\Tests\Fixture\CodedFailAction;
use Mifrial\Core\Kernel\Tests\Fixture\OkDataAction;
use Mifrial\Core\Kernel\Tests\Fixture\UnexpectedFailAction;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DispatcherActionResultTest extends TestCase
{
    /**
     * Проверяет, что данные handle оборачиваются в успешный конверт.
     *
     * @return void
     */
    public function testWrapsHandleDataIntoOkResponse(): void
    {
        $dispatcher = $this->dispatcherFor(new OkDataAction());
        $payload = $dispatcher->dispatch('demo.run', null)->toArray();

        self::assertTrue($payload['success']);
        self::assertSame(['ok' => true], $payload['data']);
    }

    /**
     * Проверяет, что ActionException становится fail с тем же кодом.
     *
     * @return void
     */
    public function testMapsActionExceptionToFailResponse(): void
    {
        $dispatcher = $this->dispatcherFor(new CodedFailAction());
        $payload = $dispatcher->dispatch('demo.run', null)->toArray();

        self::assertFalse($payload['success']);
        self::assertSame('NOT_FOUND', $payload['error']['code']);
        self::assertSame('Item not found', $payload['error']['message']);
    }

    /**
     * Проверяет, что исключение без кода действия не превращается в fail.
     *
     * @return void
     */
    public function testDoesNotCatchUnexpectedException(): void
    {
        $dispatcher = $this->dispatcherFor(new UnexpectedFailAction());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $dispatcher->dispatch('demo.run', null);
    }

    /**
     * Собирает диспетчер с одним тестовым обработчиком.
     *
     * @param IActionHandler $handler Обработчик demo.run.
     *
     * @return Dispatcher Диспетчер для проверки конверта.
     */
    private function dispatcherFor(IActionHandler $handler): Dispatcher
    {
        $moduleContainer = $this->createStub(IModuleContainer::class);
        $moduleContainer->method('get')->willReturn($handler);

        $moduleManager = $this->createStub(IModuleManager::class);
        $moduleManager->method('getRoutes')->willReturn([
            'demo.run' => [
                'handler' => $handler::class,
                'group' => 'Demo',
                'name' => 'Stub',
            ],
        ]);
        $moduleManager->method('getContainer')->willReturn($moduleContainer);

        return new Dispatcher($moduleManager);
    }
}
