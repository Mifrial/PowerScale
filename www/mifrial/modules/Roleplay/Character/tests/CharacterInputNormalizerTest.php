<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\CharacterInputNormalizer;
use PHPUnit\Framework\TestCase;

final class CharacterInputNormalizerTest extends TestCase
{
    /**
     * Trim имени и is_public из секций.
     *
     * @return void
     */
    public function testTrimNameAndVisibilitySort(): void
    {
        $normalizer = new CharacterInputNormalizer();
        self::assertSame('Hero', $normalizer->normalizeName('  Hero  '));
        self::assertSame(
            ['inventory', 'race'],
            $normalizer->normalizeVisibilityFields(['race', 'inventory']),
        );
        self::assertSame(['a' => 1], $normalizer->normalizePayload(['a' => 1]));
        self::assertSame(2, $normalizer->requirePositiveInt(2, 'x'));
        self::assertFalse($normalizer->isPublic([]));
        self::assertTrue($normalizer->isPublic(['inventory']));
    }

    /**
     * New: fields совпадает с fromNormalized.
     *
     * @return void
     */
    public function testNewCharacterFieldsRoundTrip(): void
    {
        $values = [
            'ownerUserId' => 1,
            'spaceId' => 2,
            'rulesRevision' => 3,
            'name' => '  Hero  ',
            'choices' => ['a' => 1],
            'sheet' => [],
            'visibilityFields' => ['race'],
            'ownerNotes' => 'n',
            'active' => false,
        ];
        $newCharacter = NewCharacter::fromNormalized($values);
        self::assertSame($values, $newCharacter->fields());
    }

    /**
     * Пустое имя и дубль секции.
     *
     * @return void
     */
    public function testInvalidNameAndVisibility(): void
    {
        $normalizer = new CharacterInputNormalizer();
        try {
            $normalizer->normalizeName('  ');
            self::fail('empty name must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->normalizeVisibilityFields(['race', 'race']);
            self::fail('duplicate must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->normalizePayload('x');
            self::fail('scalar payload must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $normalizer->requirePositiveInt(0, 'bad');
            self::fail('zero must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Конфликт несёт currentVersion.
     *
     * @return void
     */
    public function testConflictExposesCurrentVersion(): void
    {
        $exception = new CharacterConflictException(4);
        self::assertSame('CHARACTER_CONFLICT', $exception->getErrorCode());
        self::assertSame(4, $exception->getCurrentVersion());
    }
}
