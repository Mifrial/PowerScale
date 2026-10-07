<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Service\CharacterFormulaContexts;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

final class CharacterFormulaContextTest extends TestCase
{
    /**
     * Уровни и закупки попадают в контекст. Параметры и базы действий пустые.
     *
     * @return void
     */
    public function testBuildsContextFromSheetDocument(): void
    {
        $context = $this->contexts()->build([
            'abilityLevels' => ['strike' => 2, 'guard' => 0],
            'characteristicPurchases' => [
                ['characteristicCode' => 'strength', 'cost' => 4, 'value' => ['base' => 4, 'size' => 1, 'extra' => true]],
                ['characteristicCode' => 'agility', 'value' => ['base' => 3, 'size' => 0]],
            ],
            'money' => 9,
            'active' => true,
            'racialAbilityCodes' => ['innate'],
            'equippedModifiers' => [],
            'coveredPaths' => [],
            'characteristicPurchaseOs' => 4,
        ]);

        self::assertSame(2, $context->findAbilityLevel('strike'));
        self::assertSame(0, $context->findAbilityLevel('guard'));
        self::assertSame(0, $context->findAbilityLevel('missing'));
        $strength = $context->findCharacteristic('strength');
        self::assertInstanceOf(DimensionalNumber::class, $strength);
        self::assertSame(4, $strength->getBase());
        self::assertSame(1, $strength->getSize());
        $agility = $context->findCharacteristic('agility');
        self::assertInstanceOf(DimensionalNumber::class, $agility);
        self::assertSame(3, $agility->getBase());
        self::assertSame(0, $agility->getSize());
        self::assertNull($context->findCharacteristic('missing'));
        self::assertNull($context->findParameter('any'));
        self::assertNull($context->findActionCharacteristic('strike', 'strength'));
    }

    /**
     * Пустые карты — пустой контекст.
     *
     * @return void
     */
    public function testEmptyMapsAreValid(): void
    {
        $context = $this->contexts()->build([
            'abilityLevels' => [],
            'characteristicPurchases' => [],
        ]);

        self::assertSame(0, $context->findAbilityLevel('strike'));
        self::assertNull($context->findCharacteristic('strength'));
        self::assertNull($context->findParameter('any'));
        self::assertNull($context->findActionCharacteristic('strike', 'strength'));
    }

    /**
     * Битый документ и повтор кода не собирают контекст.
     *
     * @return void
     */
    public function testBrokenDocumentIsInvalid(): void
    {
        $contexts = $this->contexts();
        $sheets = [
            [],
            ['abilityLevels' => null, 'characteristicPurchases' => []],
            ['abilityLevels' => [], 'characteristicPurchases' => null],
            ['abilityLevels' => ['strike' => '2'], 'characteristicPurchases' => []],
            ['abilityLevels' => ['' => 1], 'characteristicPurchases' => []],
            ['abilityLevels' => [1], 'characteristicPurchases' => []],
            ['abilityLevels' => [], 'characteristicPurchases' => ['strength']],
            ['abilityLevels' => [], 'characteristicPurchases' => [['characteristicCode' => '', 'value' => ['base' => 3, 'size' => 0]]]],
            ['abilityLevels' => [], 'characteristicPurchases' => [['characteristicCode' => 'strength', 'value' => ['base' => 3]]]],
            ['abilityLevels' => [], 'characteristicPurchases' => [['characteristicCode' => 'strength', 'value' => ['base' => 3.0, 'size' => 0]]]],
            [
                'abilityLevels' => [],
                'characteristicPurchases' => [
                    ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => 0]],
                    ['characteristicCode' => 'strength', 'value' => ['base' => 5, 'size' => 1]],
                ],
            ],
        ];
        foreach ($sheets as $sheet) {
            try {
                $contexts->build($sheet);
                self::fail('broken sheet must fail');
            } catch (CharacterInvalidException $exception) {
                self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
            }
        }
    }

    /**
     * Разбор без строки персонажа.
     *
     * @return CharacterFormulaContexts Порт.
     */
    private function contexts(): CharacterFormulaContexts
    {
        $characters = $this->createMock(ICharacters::class);

        return new CharacterFormulaContexts($characters);
    }
}
