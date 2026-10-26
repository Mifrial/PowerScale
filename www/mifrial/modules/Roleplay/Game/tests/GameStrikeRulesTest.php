<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Service\GameCheckRoll;
use Mifrial\Roleplay\Game\Service\GameStrikeAmounts;
use Mifrial\Roleplay\Game\Service\GameStrikeLayers;
use Mifrial\Roleplay\Game\Service\GameStrikeRules;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use PHPUnit\Framework\TestCase;

/**
 * Помеха широкого удара берётся из спека карточки, а не из константы.
 */
final class GameStrikeRulesTest extends TestCase
{
    /**
     * Дельта карточки умножается на число целей сверх первой.
     *
     * @return void
     */
    public function testWidePenaltyScalesFromCard(): void
    {
        $rules = $this->rules(-1);

        $three = $rules->wideTargetCheckAdvantage(1, 1, 'wide-card', 3);
        self::assertNotNull($three);
        self::assertSame('circumstances', $three->getSourceCode());
        self::assertSame(-2, $three->getDelta());
        self::assertSame(-1, $rules->wideTargetCheckAdvantage(1, 1, 'wide-card', 2)?->getDelta());
        self::assertNull($rules->wideTargetCheckAdvantage(1, 1, 'wide-card', 1));
    }

    /**
     * Карточка с дельтой −2 даёт вдвое большую помеху.
     *
     * @return void
     */
    public function testWidePenaltyUsesCardDelta(): void
    {
        self::assertSame(-4, $this->rules(-2)->wideTargetCheckAdvantage(1, 1, 'wide-card', 3)?->getDelta());
    }

    /**
     * Без поля в карточке помехи нет.
     *
     * @return void
     */
    public function testWidePenaltyIsAbsentWithoutCardField(): void
    {
        self::assertNull($this->rules(null)->wideTargetCheckAdvantage(1, 1, 'wide-card', 3));
    }

    /**
     * Собирает правила с одной карточкой действия.
     *
     * @param int|null $wideAdvantage Дельта помехи.
     *
     * @return GameStrikeRules Правила.
     */
    private function rules(?int $wideAdvantage): GameStrikeRules
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [$this->card($wideAdvantage)], []);
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($slice);
        $characters = $this->createMock(ICharacters::class);
        $mechanics = $this->createMock(IMechanics::class);
        $sheets = $this->createMock(ICharacterSheets::class);
        $checkRoll = new GameCheckRoll(
            $slices,
            $characters,
            $mechanics,
            $this->createMock(IMechanicRolls::class),
            $sheets,
        );
        $layers = new GameStrikeLayers(
            $this->createMock(ICharacterCombatLayers::class),
            $this->createMock(IMechanicEngine::class),
            $mechanics,
            new GameStrikeAmounts(),
        );

        return new GameStrikeRules(
            $slices,
            $characters,
            $this->createMock(ICharacterFormulaContexts::class),
            $this->createMock(IFormulaEvaluations::class),
            $checkRoll,
            $layers,
            $sheets,
        );
    }

    /**
     * Карточка широкого удара.
     *
     * @param int|null $wideAdvantage Дельта помехи.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function card(?int $wideAdvantage): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                'wide-card',
                true,
                'ability',
                'Широкий удар',
                '',
                [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                    'requirements' => [],
                    'grants' => [],
                    'attack_mode' => 'wide',
                    'max_targets' => 3,
                    'wide_target_check_advantage' => $wideAdvantage,
                ],
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            [],
        );
    }
}
