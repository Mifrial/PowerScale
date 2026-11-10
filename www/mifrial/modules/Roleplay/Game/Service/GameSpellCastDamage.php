<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Service\Sheet\CharacterKeywordExperience;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AutoHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDamage;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Урон заклинания: опыт, ступень, falloff, сопротивление и последствия.
 */
final class GameSpellCastDamage
{
    /**
     * Создаёт расчёт урона.
     *
     * @param GameStrikeRules $rules Срез и данные.
     * @param CharacterKeywordExperience $experience Опыт признака.
     * @param GameStrikeAmounts $amounts Арифметика урона.
     * @param GameSpellMagicResistance $magicResistance Сопротивление магии.
     * @param GameStrikeConsequences $consequences Последствия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly CharacterKeywordExperience $experience,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameSpellMagicResistance $magicResistance,
        private readonly GameStrikeConsequences $consequences,
    ) {
    }

    /**
     * Считает урон и операции листа цели.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param bool $castOk Проверка сотворения пройдена.
     * @param int|null $deliverySr Успех атаки касания; null — автопопадание.
     *
     * @return array{
     *     spellSr: int|null,
     *     spellDamage: DimensionalNumber|null,
     *     injury: DimensionalNumber|null,
     *     operations: list<array<string, mixed>>,
     *     consequence: array<string, mixed>
     * } Итог урона.
     *
     * @throws GameInvalidException Если тип, срез или лист битые.
     */
    public function apply(
        GameRecord $game,
        GameSpellCastPrepared $prepared,
        bool $castOk,
        ?int $deliverySr = null,
    ): array {
        $damage = $prepared->spell->getSpell()->getDamage();
        $resolution = $prepared->spell->getBase()->getHitResolution();
        $rating = $deliverySr ?? ($resolution instanceof AutoHit ? $resolution->getRating() : null);
        $cap = $damage === null
            ? null
            : $this->rules->outcome->successRatingCap(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $damage->getDamageTypeCode(),
            );
        $spellSr = $castOk && $rating !== null && $rating > 0 ? $this->amounts->appliedSr($rating, $cap) : null;
        $empty = [
            'spellSr' => $spellSr,
            'spellDamage' => null,
            'injury' => null,
            'operations' => [],
            'consequence' => [],
        ];
        if ($damage === null || $prepared->damageTarget === null || $spellSr === null) {
            return $empty;
        }

        $amount = $this->amount($prepared, $damage);
        if (!$amount instanceof DimensionalNumber) {
            return $empty;
        }

        $target = $prepared->damageTarget;
        $resistance = $this->rules->defense->resistanceByType(
            $game,
            $damage->getDamageTypeCode(),
            $target['sheet'],
            $target['choices'],
        );
        $penetration = $prepared->upgrades->getResistancePenetration();
        if (
            $penetration > 0
            && $this->magicResistance->isMagicType($prepared->slice, $damage->getDamageTypeCode())
        ) {
            $resistance = $this->amounts->effectiveDefense(
                $resistance,
                new DimensionalNumber($penetration, 0),
            );
        }

        $injury = $this->amounts->injury($amount, $resistance, $spellSr, null);
        $split = $this->rules->outcome->splitInjury(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $target['sheet'] : null,
            $injury,
            $target['type'] === 'npc' ? $target['choices'] : null,
            $target['type'] === 'npc' ? $target['version'] : null,
        );
        $endurance = $this->rules->data->enduranceOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $target['sheet'] : null,
            $target['type'] === 'npc' ? $target['choices'] : null,
            $target['type'] === 'npc' ? $target['version'] : null,
        );
        $effects = $this->consequences->effects(
            $game,
            $target['sheet'],
            $injury,
            $spellSr,
            $split,
            $damage->getDamageTypeCode(),
            true,
            $endurance,
        );

        return [
            'spellSr' => $spellSr,
            'spellDamage' => $amount,
            'injury' => $injury,
            'operations' => $effects['operations'],
            'consequence' => $effects['consequence'],
        ];
    }

    /**
     * Урон от мощи, ступени опыта и falloff.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param SpellDamage $damage Урон карточки.
     *
     * @return DimensionalNumber|null Урон или null.
     *
     * @throws GameInvalidException Если опыт или мощь битые.
     */
    private function amount(GameSpellCastPrepared $prepared, SpellDamage $damage): ?DimensionalNumber
    {
        $levels = $prepared->casterSheet['abilityLevels'] ?? [];
        $known = [];
        foreach (is_array($levels) ? $levels : [] as $code => $level) {
            if (is_string($code) && is_int($level)) {
                $known[$code] = $level;
            }
        }

        $experience = $this->experience->experience(
            $prepared->slice,
            $prepared->casterModel,
            $damage->getExperienceKeywordCode(),
            $known,
        );
        $modify = $this->modify($damage, $experience);
        $amount = (new CharacteristicNumber(
            $prepared->spellPower->getBase(),
            $prepared->spellPower->getSize(),
        ))->modify($modify);

        return $this->falloff($amount, $damage, $prepared->distanceIpari);
    }

    /**
     * Модификатор ступени по опыту.
     *
     * @param SpellDamage $damage Урон карточки.
     * @param int $experience Опыт.
     *
     * @return int Модификатор.
     */
    private function modify(SpellDamage $damage, int $experience): int
    {
        $steps = $damage->getSteps();
        $chosen = null;
        foreach ($steps as $step) {
            if ($step->getMinExperience() > $experience) {
                continue;
            }

            if ($chosen === null || $step->getMinExperience() > $chosen->getMinExperience()) {
                $chosen = $step;
            }
        }

        if ($chosen !== null) {
            return $chosen->getModify();
        }

        return $steps === [] ? 0 : $steps[0]->getModify();
    }

    /**
     * Снижение урона по дистанции.
     *
     * @param CharacteristicNumber $amount Урон.
     * @param SpellDamage $damage Урон карточки.
     * @param int $distanceIpari Дистанция в ипари.
     *
     * @return CharacteristicNumber|null Урон или null ниже минимума.
     */
    private function falloff(
        CharacteristicNumber $amount,
        SpellDamage $damage,
        int $distanceIpari,
    ): ?CharacteristicNumber {
        $free = $damage->getFreeIpari();
        $perExtra = $damage->getSizePerExtraIpari();
        if ($free === null || $perExtra === null) {
            return $amount;
        }

        $extra = max(0, $distanceIpari - $free);
        $reduced = $extra === 0 ? $amount : $amount->modify(-3 * $extra * $perExtra);
        $min = $damage->getMin();
        if ($min instanceof DimensionalNumber && $reduced->characteristicDiffTo($min) < 0) {
            return null;
        }

        return $reduced;
    }
}
