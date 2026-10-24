<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use LogicException;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\ILogger;
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
        self::assertArrayNotHasKey('trace', $payload['error']);
    }

    /**
     * При debug в details есть стек верхнего отказа и previous.
     *
     * @return void
     */
    public function testDebugActionExceptionIncludesPreviousTrace(): void
    {
        $entries = [];
        $handler = new class () implements IActionHandler {
            public function handle(): never
            {
                throw new ActionException('GAME_INVALID', 'outer', new LogicException('inner'));
            }
        };
        $dispatcher = $this->dispatcherFor($handler, $this->recordingLogger($entries), true);
        $payload = $dispatcher->dispatch('demo.run', null)->toArray();

        self::assertSame('GAME_INVALID', $payload['error']['code']);
        self::assertStringContainsString('ActionException: outer', $payload['error']['details']['trace']);
        self::assertStringContainsString('LogicException: inner', $payload['error']['details']['trace']);
        self::assertSame('Action failed', $entries[0]['message']);
        self::assertSame('demo.run', $entries[0]['context']['source']);
        self::assertStringContainsString('LogicException: inner', $entries[0]['context']['trace']);
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
     * @param ILogger|null $logger Журнал доменных отказов.
     * @param bool $debug Класть trace в JSON.
     *
     * @return Dispatcher Диспетчер для проверки конверта.
     */
    private function dispatcherFor(
        IActionHandler $handler,
        ?ILogger $logger = null,
        bool $debug = false,
    ): Dispatcher {
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

        return new Dispatcher($moduleManager, logger: $logger, debug: $debug);
    }

    /**
     * Логер, складывающий error в массив.
     *
     * @param array<int, array{level: string, message: string, context: array<string, mixed>}> $entries Записи.
     *
     * @return ILogger Логер теста.
     */
    private function recordingLogger(array &$entries): ILogger
    {
        return new class ($entries) implements ILogger {
            /**
             * @param array<int, array{level: string, message: string, context: array<string, mixed>}> $entries Записи.
             */
            public function __construct(private array &$entries)
            {
            }

            public function error(string $message, array $context = []): void
            {
                $this->entries[] = ['level' => 'error', 'message' => $message, 'context' => $context];
            }

            public function warning(string $message, array $context = []): void
            {
            }

            public function info(string $message, array $context = []): void
            {
            }

            public function debug(string $message, array $context = []): void
            {
            }
        };
    }
}
