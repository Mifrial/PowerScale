<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterOpening;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Character\Service\Read\CharacterViewAssembler;
use PHPUnit\Framework\TestCase;

final class CharacterSectionMaskTest extends TestCase
{
    /**
     * Чужой видит только ключи выданных секций и active.
     *
     * @return void
     */
    public function testMaskKeepsGrantedKeysOnly(): void
    {
        $mask = new CharacterSectionMask();
        $masked = $mask->apply(
            [
                'name' => 'Hero',
                'shortDescription' => 'Brief',
                'raceCode' => 'elf',
                'inventory' => [['ruleCode' => 'sword']],
                'limits' => ['os' => 1],
                'customRules' => [['id' => 1]],
                'ageYears' => 20,
            ],
            [
                'racialAbilityCodes' => ['sight'],
                'equippedModifiers' => [],
                'money' => 3,
                'active' => true,
            ],
            ['race', 'inventory'],
        );

        self::assertSame([
            'raceCode' => 'elf',
            'inventory' => [['ruleCode' => 'sword']],
        ], $masked['choices']);
        self::assertSame([
            'racialAbilityCodes' => ['sight'],
            'equippedModifiers' => [],
            'active' => true,
        ], $masked['sheet']);
    }

    /**
     * JSON чужого без заметок и закрытых ключей.
     *
     * @return void
     */
    public function testStrangerDetailOmitsOwnerFields(): void
    {
        $assembler = new CharacterViewAssembler(new CharacterSectionMask());
        $record = CharacterRecord::fromNormalized([
            'id' => 4,
            'owner_id' => 1,
            'space_id' => 2,
            'rules_revision' => 3,
            'name' => 'Hero',
            'active' => true,
            'actual_version' => 2,
            'choices' => [
                'name' => 'Hero',
                'limits' => ['os' => 1],
                'customRules' => [],
                'inventory' => [],
            ],
            'sheet' => ['active' => true, 'money' => 1],
            'visibility_fields' => ['inventory'],
            'is_public' => true,
            'owner_notes' => 'secret',
            'created_at' => DateTime::now(),
            'updated_at' => DateTime::now(),
        ]);
        $view = $assembler->detail(new CharacterOpening($record, false, ['inventory'], []));

        self::assertSame('Hero', $view['name']);
        self::assertSame(['inventory'], $view['visibleSections']);
        self::assertSame(['inventory' => []], $view['choices']);
        self::assertArrayNotHasKey('ownerNotes', $view);
        self::assertArrayNotHasKey('actualVersion', $view);
        self::assertArrayNotHasKey('limits', $view['choices']);
        self::assertArrayNotHasKey('name', $view['choices']);
    }
}
