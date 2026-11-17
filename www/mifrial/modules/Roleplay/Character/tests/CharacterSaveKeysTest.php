<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveKeys;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет белый список ключей сохранения.
 */
final class CharacterSaveKeysTest extends TestCase
{
    /**
     * Тип и слоты знания допустимы в строке способности.
     *
     * @return void
     */
    public function testKnowledgeKeysAreAllowed(): void
    {
        $keys = new CharacterSaveKeys();

        $keys->assertLists([
            [
                'ruleCode' => 'znanie',
                'level' => 2,
                'fieldCode' => 'physiology',
                'slots' => ['species' => ['code' => 'elf', 'text' => 'Эльфы']],
            ],
        ], [], [], []);

        self::assertTrue(true);
    }

    /**
     * Неизвестный ключ строки способности отклоняется.
     *
     * @return void
     */
    public function testUnknownAbilityKeyIsRejected(): void
    {
        $keys = new CharacterSaveKeys();

        $this->expectException(ActionException::class);
        $keys->assertLists([['ruleCode' => 'znanie', 'level' => 1, 'unknown' => true]], [], [], []);
    }
}
