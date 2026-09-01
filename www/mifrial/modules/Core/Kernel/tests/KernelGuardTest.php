<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Container\ModuleContainer;
use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Exception\ModuleManager\InvalidModuleConfigException;
use Mifrial\Core\Kernel\Http\CsrfGuard;
use Mifrial\Core\Kernel\Http\DebugResponseFormatter;
use Mifrial\Core\Kernel\Http\HttpStatusMapper;
use Mifrial\Core\Kernel\Http\ResponseEmitter;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Service\ModuleManager;
use Mifrial\Core\Kernel\Service\ServiceLocator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class KernelGuardTest extends TestCase
{
    /**
     * Проверяет double-submit CSRF.
     *
     * @return void
     */
    public function testCsrfGuardAcceptsMatchingTokens(): void
    {
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieValue')->willReturn('secret');
        $httpRequest->method('getHeader')->willReturn('secret');

        self::assertTrue((new CsrfGuard())->isValid($httpRequest));
    }

    /**
     * Проверяет отказ CSRF при пустых токенах.
     *
     * @return void
     */
    public function testCsrfGuardRejectsMismatch(): void
    {
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieValue')->willReturn('a');
        $httpRequest->method('getHeader')->willReturn('b');

        self::assertFalse((new CsrfGuard())->isValid($httpRequest));
    }

    /**
     * Проверяет HTTP-коды конверта.
     *
     * @return void
     */
    public function testHttpStatusMapper(): void
    {
        $mapper = new HttpStatusMapper();

        self::assertSame(200, $mapper->statusFor(ActionResponse::ok(['ok' => true])));
        self::assertSame(400, $mapper->statusFor(ActionResponse::fail('UNKNOWN_ACTION', 'x')));
        self::assertSame(403, $mapper->statusFor(ActionResponse::fail('CSRF', 'x')));
        self::assertSame(500, $mapper->statusFor(ActionResponse::fail('INTERNAL', 'x')));
    }

    /**
     * Проверяет debug INTERNAL с trace.
     *
     * @return void
     */
    public function testDebugInternalIncludesTrace(): void
    {
        $response = (new DebugResponseFormatter(true))->internalError(new RuntimeException('boom'));
        $payload = $response->toArray();

        self::assertSame('INTERNAL', $payload['error']['code']);
        self::assertStringContainsString('RuntimeException', $payload['error']['message']);
        self::assertArrayHasKey('trace', $payload['error']);
    }

    /**
     * Проверяет маскирование INTERNAL без debug.
     *
     * @return void
     */
    public function testProductionInternalHidesDetails(): void
    {
        $payload = (new DebugResponseFormatter(false))->internalError(new RuntimeException('boom'))->toArray();

        self::assertSame('Internal error', $payload['error']['message']);
        self::assertArrayNotHasKey('trace', $payload['error']);
    }

    /**
     * Проверяет сериализацию JSON без exit.
     *
     * @return void
     */
    public function testEmitterEncodeJson(): void
    {
        $json = (new ResponseEmitter())->encodeJson(ActionResponse::ok(['ok' => true]));

        self::assertSame('{"success":true,"data":{"ok":true}}', $json);
    }

    /**
     * Проверяет цикл порта контейнера.
     *
     * @return void
     */
    public function testCircularPortThrows(): void
    {
        $locator = new ServiceLocator();
        $container = new ModuleContainer($locator, [
            'loop' => static function (object $serviceLocator, ModuleContainer $moduleContainer): object {
                return $moduleContainer->get('loop');
            },
        ]);

        $this->expectException(KernelException::class);
        $container->get('loop');
    }

    /**
     * Проверяет, что фабрика локатора должна вернуть контейнер.
     *
     * @return void
     */
    public function testLocatorFactoryMustReturnContainer(): void
    {
        $locator = new ServiceLocator();
        $locator->set('bad', static fn (): object => new stdClass());

        $this->expectException(KernelException::class);
        $locator->get('bad');
    }

    /**
     * Проверяет исключение при отсутствии каталога Core.
     *
     * @return void
     */
    public function testLoadCoreMissingDirectoryThrows(): void
    {
        $manager = new ModuleManager(sys_get_temp_dir() . '/mifrial-missing-' . uniqid());

        $this->expectException(KernelException::class);
        $manager->loadCore();
    }

    /**
     * Проверяет конфликт кодов action при загрузке Core.
     *
     * @return void
     */
    public function testDuplicateActionThrowsOnLoad(): void
    {
        $modulesRoot = sys_get_temp_dir() . '/mifrial-dup-' . uniqid();
        mkdir($modulesRoot . '/Core/One', 0777, true);
        mkdir($modulesRoot . '/Core/Two', 0777, true);
        $route = '<?php return ["routes" => ["dup.act" => ["handler" => "H"]]];';
        file_put_contents($modulesRoot . '/Core/One/module.config.php', $route);
        file_put_contents($modulesRoot . '/Core/Two/module.config.php', $route);

        $this->expectException(InvalidModuleConfigException::class);
        (new ModuleManager($modulesRoot))->loadCore();
    }
}
