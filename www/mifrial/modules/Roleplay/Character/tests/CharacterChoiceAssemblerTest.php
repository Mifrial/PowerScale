<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use PHPUnit\Framework\TestCase;

/**
 * План M10: payload строки инвентаря читается без потерь.
 */
final class CharacterChoiceAssemblerTest extends TestCase
{
    /**
     * Объём и записи заклинаний доходят до модели.
     *
     * @return void
     */
    public function testInventoryPayloadIsRead(): void
    {
        $choices = (new CharacterChoiceAssembler())->assemble(
            'Имя',
            '',
            [],
            [[
                'ruleCode' => 'magic-crystal',
                'payload' => [
                    'volume' => 30,
                    'recorded_spells' => [['ruleCode' => 'spark', 'level' => 1]],
                ],
            ]],
            [],
            [],
            true,
        );
        $item = $choices->getInventory()[0];

        self::assertSame([
            'volume' => 30,
            'recorded_spells' => [['ruleCode' => 'spark', 'level' => 1]],
        ], $item->getPayload());
    }

    /**
     * Нет payload — пустая карта.
     *
     * @return void
     */
    public function testMissingPayloadIsEmpty(): void
    {
        $choices = (new CharacterChoiceAssembler())->assemble(
            'Имя',
            '',
            [],
            [['ruleCode' => 'sword']],
            [],
            [],
            true,
        );

        self::assertSame([], $choices->getInventory()[0]->getPayload());
    }
}
