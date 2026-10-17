<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Setup\FoldArmorCharacteristicFieldsStep;
use PHPUnit\Framework\TestCase;

/**
 * Сворачивает старые поля брони в grants и characteristic_limits.
 */
final class FoldArmorCharacteristicFieldsStepTest extends TestCase
{
    /**
     * Штраф и потолок уходят в generic-форму.
     *
     * @return void
     */
    public function testFoldsItemArmorFields(): void
    {
        $next = $this->step()->foldDocument([
            'armor' => [
                'strength_penalty' => -2,
                'max_agility' => ['base' => 5, 'size' => -1],
                'characteristic_limits' => [],
            ],
        ], 'item');

        self::assertIsArray($next);
        self::assertArrayNotHasKey('strength_penalty', $next['armor']);
        self::assertArrayNotHasKey('max_agility', $next['armor']);
        self::assertSame([
            [
                'type' => 'characteristic_modify',
                'characteristic_code' => 'strength',
                'source_code' => 'armor',
                'check_codes' => [],
                'amount' => -2,
                'permanent' => true,
            ],
        ], $next['grants']);
        self::assertSame([
            [
                'characteristic_code' => 'dexterity',
                'limit' => ['type' => 'dimensional', 'base' => 5, 'size' => -1],
            ],
        ], $next['armor']['characteristic_limits']);
    }

    /**
     * Ops модификатора меняют type, числа оставляют.
     *
     * @return void
     */
    public function testFoldsModifierOperationsAndEffects(): void
    {
        $next = $this->step()->foldDocument([
            'operations' => [[
                'type' => 'strength_penalty',
                'add' => -1,
                'when' => ['keyword_all' => ['armor-item']],
                'source_code' => 'mass',
            ]],
            'effects' => [[
                'ops' => [[
                    'type' => 'max_agility',
                    'delta' => -1,
                    'when' => ['keyword_all' => ['armor-item']],
                    'source_code' => 'mass',
                ]],
            ]],
        ], 'item_modifier');

        self::assertIsArray($next);
        self::assertSame('characteristic_modify', $next['operations'][0]['type']);
        self::assertSame('strength', $next['operations'][0]['characteristic_code']);
        self::assertSame(-1, $next['operations'][0]['add']);
        self::assertSame('mass', $next['operations'][0]['source_code']);
        self::assertSame('characteristic_limit', $next['effects'][0]['ops'][0]['type']);
        self::assertSame('dexterity', $next['effects'][0]['ops'][0]['characteristic_code']);
        self::assertSame(-1, $next['effects'][0]['ops'][0]['delta']);
    }

    /**
     * Повторный прогон без старых ключей — no-op.
     *
     * @return void
     */
    public function testSecondPassIsNoop(): void
    {
        $step = $this->step();
        $first = $step->foldDocument([
            'armor' => ['strength_penalty' => -1, 'characteristic_limits' => []],
        ], 'item');
        self::assertIsArray($first);
        self::assertNull($step->foldDocument($first, 'item'));
    }

    /**
     * Уже есть limit с тем же кодом — отказ.
     *
     * @return void
     */
    public function testDuplicateLimitThrows(): void
    {
        $this->expectException(RuleInvalidException::class);
        $this->step()->foldDocument([
            'armor' => [
                'max_agility' => ['base' => 3, 'size' => 0],
                'characteristic_limits' => [[
                    'characteristic_code' => 'dexterity',
                    'limit' => ['type' => 'dimensional', 'base' => 4, 'size' => 0],
                ]],
            ],
        ], 'item', 9);
    }

    /**
     * @return FoldArmorCharacteristicFieldsStep Шаг.
     */
    private function step(): FoldArmorCharacteristicFieldsStep
    {
        return new FoldArmorCharacteristicFieldsStep($this->createMock(ISmartTableGateway::class));
    }
}
