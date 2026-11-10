<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;

/**
 * Сопротивление магии: типы урона, меняющие сложность сотворения.
 */
final class GameSpellMagicResistance
{
    /**
     * Создаёт расчёт.
     *
     * @param GameStrikeDefense $defense Боевое сопротивление.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeDefense $defense,
    ) {
    }

    /**
     * Сумма сопротивления всем магическим типам урона.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $sheet Лист цели.
     * @param array<string, mixed> $choices Выборы цели.
     *
     * @return int Сопротивление.
     *
     * @throws GameInvalidException Если тип или слой битые.
     */
    public function sumResistance(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
    ): int {
        $total = 0;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof DamageTypeSpec || !$spec->isModifiesSpellDifficulty()) {
                continue;
            }

            $total += $this->defense->resistanceByType(
                $game,
                $rule->getCode(),
                $sheet,
                $choices,
            )->toInteger();
        }

        return $total;
    }

    /**
     * Проверяет, что тип урона магический.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param string $damageTypeCode Тип урона.
     *
     * @return bool true, если тип меняет сложность сотворения.
     */
    public function isMagicType(CharacterRuleSlice $slice, string $damageTypeCode): bool
    {
        $rule = $slice->findLive($damageTypeCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof DamageTypeSpec && $spec->isModifiesSpellDifficulty();
    }
}
