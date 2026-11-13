<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Tests;

use Mifrial\Library\Asset\Exception\AssetInvalidException;
use Mifrial\Library\Asset\Service\AssetInputValidator;
use PHPUnit\Framework\TestCase;

final class AssetInputValidatorTest extends TestCase
{
    private AssetInputValidator $validator;

    /**
     * Готовит валидатор.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->validator = new AssetInputValidator();
    }

    /**
     * Ключи коллекций и подписи.
     *
     * @return void
     */
    public function testCollectionFields(): void
    {
        self::assertSame('Roleplay/Scene', $this->validator->normalizeModule(' Roleplay/Scene '));
        self::assertSame('decor', $this->validator->normalizeCode(' decor '));
        self::assertSame('Декор', $this->validator->normalizeCollectionTitle(' Декор '));

        $this->assertInvalid(fn (): string => $this->validator->normalizeModule('Scene'));
        $this->assertInvalid(fn (): string => $this->validator->normalizeModule('Roleplay/Scene/Extra'));
        $this->assertInvalid(fn (): string => $this->validator->normalizeCode('Decor'));
        $this->assertInvalid(fn (): string => $this->validator->normalizeCode(''));
        $this->assertInvalid(fn (): string => $this->validator->normalizeCollectionTitle('   '));
    }

    /**
     * Имя ассета и имя по умолчанию.
     *
     * @return void
     */
    public function testName(): void
    {
        self::assertSame('Дерево', $this->validator->normalizeName(' Дерево ', 'tree.png'));
        self::assertSame('tree', $this->validator->normalizeName(null, 'tree.png'));
        self::assertSame('tree', $this->validator->normalizeName('   ', 'tree.png'));
        self::assertSame('hero.v2', $this->validator->normalizeName(null, 'hero.v2.png'));

        $this->assertInvalid(fn (): string => $this->validator->normalizeName(null, ''));
        $this->assertInvalid(fn (): string => $this->validator->normalizeName(str_repeat('a', 121), 'tree.png'));
    }

    /**
     * Поиск и границы страницы.
     *
     * @return void
     */
    public function testPageFields(): void
    {
        self::assertNull($this->validator->normalizeSearchQuery(null));
        self::assertNull($this->validator->normalizeSearchQuery('   '));
        self::assertSame('дерево', $this->validator->normalizeSearchQuery(' дерево '));

        $this->assertInvalid(fn (): ?string => $this->validator->normalizeSearchQuery(str_repeat('a', 121)));
        $this->validator->assertPageBounds(1, 0);
        $this->validator->assertPageBounds(100, 10);
        $this->assertInvalid(function (): void {
            $this->validator->assertPageBounds(0, 0);
        });
        $this->assertInvalid(function (): void {
            $this->validator->assertPageBounds(101, 0);
        });
        $this->assertInvalid(function (): void {
            $this->validator->assertPageBounds(10, -1);
        });
    }

    /**
     * Ожидает ASSET_INVALID.
     *
     * @param callable $operation Операция.
     *
     * @return void
     */
    private function assertInvalid(callable $operation): void
    {
        try {
            $operation();
        } catch (AssetInvalidException $exception) {
            self::assertSame('ASSET_INVALID', $exception->getErrorCode());

            return;
        }

        self::fail('AssetInvalidException was not thrown');
    }
}
