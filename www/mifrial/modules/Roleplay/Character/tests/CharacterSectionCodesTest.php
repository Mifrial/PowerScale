<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionCodes;
use Mifrial\Roleplay\Character\Service\Read\CharacterViewerParser;
use PHPUnit\Framework\TestCase;

final class CharacterSectionCodesTest extends TestCase
{
    /**
     * Запись сортирует enum и отвергает дубль и чужой код.
     *
     * @return void
     */
    public function testNormalizeSortsAndRejectsUnknown(): void
    {
        $codes = new CharacterSectionCodes();
        self::assertSame(['inventory', 'race'], $codes->normalize([' race ', 'inventory']));

        try {
            $codes->normalize(['race', 'race']);
            self::fail('duplicate must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $codes->normalize(['gm']);
            self::fail('unknown code must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Чтение отбрасывает код вне enum.
     *
     * @return void
     */
    public function testKeepKnownDropsUnknown(): void
    {
        $codes = new CharacterSectionCodes();
        self::assertSame(['inventory'], $codes->keepKnown(['nope', 'inventory', 'inventory']));
    }

    /**
     * Пустые поля зрителя и повтор userId не проходят.
     *
     * @return void
     */
    public function testViewerParserRejectsEmptyAndDuplicate(): void
    {
        $parser = new CharacterViewerParser(new CharacterSectionCodes());
        $grants = $parser->parse([
            ['userId' => 2, 'fields' => ['inventory', 'race']],
        ]);
        self::assertSame(2, $grants[0]->getUserId());
        self::assertSame(['inventory', 'race'], $grants[0]->getFields());

        try {
            $parser->parse([['userId' => 2, 'fields' => []]]);
            self::fail('empty fields must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }

        try {
            $parser->parse([
                ['userId' => 2, 'fields' => ['race']],
                ['userId' => 2, 'fields' => ['inventory']],
            ]);
            self::fail('duplicate viewer must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }
}
