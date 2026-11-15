<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheetEngines;
use Mifrial\Roleplay\Game\Service\GameNpcSheetRefresh;
use PHPUnit\Framework\TestCase;

/**
 * Пересчёт листа NPC при чтении.
 */
final class GameNpcSheetRefreshTest extends TestCase
{
    /**
     * Состояния боя из сохранённого листа не теряются при пересборке.
     *
     * @return void
     */
    public function testKeepsRuntimeStates(): void
    {
        $states = [[
            'id' => 1,
            'stateRuleCode' => 'electrocharge',
            'value' => 1,
            'boundSourceKey' => 'inventory:5',
        ]];
        $engine = $this->engine([
            'sheet' => [
                'characteristics' => [],
                'money' => 0,
            ],
        ]);

        $version = (new GameNpcSheetRefresh($engine))->apply([
            'spaceId' => 13,
            'rulesRevision' => 50,
            'choices' => ['name' => 'Колдунец'],
            'sheet' => ['states' => $states, 'money' => 0],
        ]);

        self::assertSame($states, $version['sheet']['states']);
    }

    /**
     * Движок, возвращающий заданный лист.
     *
     * @param array<string, mixed> $report Отчёт.
     *
     * @return ICharacterSheetEngines Движок.
     */
    private function engine(array $report): ICharacterSheetEngines
    {
        return new class ($report) implements ICharacterSheetEngines {
            /**
             * @param array<string, mixed> $report Отчёт.
             */
            public function __construct(private readonly array $report)
            {
            }

            public function build(
                array $choices,
                int $spaceId,
                int $revision,
                ?array $expectedSheet,
                ?array $previousSheet = null,
            ): array {
                return ['kind' => 'ok'] + $this->report;
            }

            public function remap(
                array $choices,
                int $spaceId,
                int $sourceRevision,
                int $targetRevision,
                ?array $previousSheet = null,
            ): array {
                return ['kind' => 'ok'] + $this->report;
            }
        };
    }
}
