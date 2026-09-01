<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Exception\Field\UnknownHydratorException;
use Mifrial\Core\SmartTable\Interface\Field\IFieldHydrator;
use Mifrial\Core\SmartTable\Service\HydratorRegistry;
use PHPUnit\Framework\TestCase;

final class HydratorRegistryTest extends TestCase
{
    /**
     * Проверяет регистрацию hydrator.
     *
     * @return void
     */
    public function testRegisterAndGet(): void
    {
        $registry = new HydratorRegistry();
        $hydrator = new class () implements IFieldHydrator {
            public function hydrate(mixed $decodedValue): mixed
            {
                return $decodedValue;
            }

            public function extract(mixed $domainValue): mixed
            {
                return $domainValue;
            }
        };
        $registry->register('identity', $hydrator);

        self::assertSame($hydrator, $registry->get('identity'));
    }

    /**
     * Проверяет отказ на неизвестный код hydrator.
     *
     * @return void
     */
    public function testUnknownHydratorFails(): void
    {
        try {
            (new HydratorRegistry())->get('missing');
            self::fail('unknown hydrator must fail');
        } catch (UnknownHydratorException $exception) {
            self::assertSame('HYDRATOR_UNKNOWN', $exception->getErrorCode());
        }
    }
}
