<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Tests;

use Mifrial\Library\Asset\Exception\AssetInvalidException;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Roleplay\Scene\Setup\SeedSceneAssetCollectionsStep;
use PHPUnit\Framework\TestCase;

final class SeedSceneAssetCollectionsStepTest extends TestCase
{
    /**
     * Шаг заводит декор и террейн и повторяется без дублей.
     *
     * @return void
     */
    public function testSeedIsIdempotent(): void
    {
        $collections = new class () implements IAssetCollections {
            /**
             * @var array<int, array{module: string, code: string, title: string}>
             */
            public array $calls = [];

            /**
             * @var array<string, array{module: string, code: string, title: string}>
             */
            public array $ensured = [];

            /**
             * Запоминает вызов и уникальную коллекцию.
             *
             * @param string $module Модуль.
             * @param string $code Код.
             * @param string $title Подпись.
             *
             * @return void
             */
            public function ensure(string $module, string $code, string $title): void
            {
                $this->calls[] = ['module' => $module, 'code' => $code, 'title' => $title];
                $this->ensured[$module . ':' . $code] = ['module' => $module, 'code' => $code, 'title' => $title];
            }
        };
        $step = new SeedSceneAssetCollectionsStep($collections);

        $step->run();
        $step->run();

        self::assertSame('Roleplay/Scene:seed.asset-collections', $step->getId());
        self::assertSame(
            [
                ['module' => 'Roleplay/Scene', 'code' => 'decor', 'title' => 'Декор'],
                ['module' => 'Roleplay/Scene', 'code' => 'terrain', 'title' => 'Текстуры террейна'],
            ],
            array_values($collections->ensured),
        );
        self::assertCount(4, $collections->calls);
    }

    /**
     * Ошибка порта не глотается.
     *
     * @return void
     */
    public function testPortErrorIsPropagated(): void
    {
        $collections = new class () implements IAssetCollections {
            /**
             * Всегда отказывает.
             *
             * @param string $module Модуль.
             * @param string $code Код.
             * @param string $title Подпись.
             *
             * @return void
             *
             * @throws AssetInvalidException Всегда.
             */
            public function ensure(string $module, string $code, string $title): void
            {
                throw new AssetInvalidException('bad collection');
            }
        };
        $step = new SeedSceneAssetCollectionsStep($collections);

        $this->expectException(AssetInvalidException::class);
        $step->run();
    }
}
