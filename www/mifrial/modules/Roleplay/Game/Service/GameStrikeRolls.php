<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameStrikeBlockRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeDodgeRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeHitRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\DodgeRangedPenalty;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackAccuracyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\BlockProfile;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Броски удара: попадание, уклон и блок.
 */
final class GameStrikeRolls
{
    /**
     * Принимает бросок, участников, снаряжение и мастерство.
     *
     * @param GameCheckRoll $hitRoll Бросок попадания.
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники удара.
     * @param GameStrikeEquipment $equipment Снаряжение удара.
     * @param GameStrikeMastery $mastery Мастерство оружия.
     * @param GameStrikeChecks $checks Проверки выбора.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckRoll $hitRoll,
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeParticipants $participants,
        private readonly GameStrikeEquipment $equipment,
        private readonly GameStrikeMastery $mastery,
        private readonly GameStrikeChecks $checks,
    ) {
    }

    /**
     * Бросает попадание с мастерством выбранного предмета.
     *
     * @param GameStrikeHitRequest $request Запрос броска.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Результат.
     *
     * @throws GameInvalidException Если документ или mastery невалидны.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    public function hit(GameStrikeHitRequest $request): array
    {
        $target = $request->target;
        $profile = $target->profile;
        $documents = $this->documents($target);
        $item = $this->equipment->selectedItem(
            $profile->spaceId,
            $profile->rulesRevision,
            $documents['choices'],
            $profile->itemInventoryId,
            $profile->itemRuleCode,
        );
        $slice = $this->slices->slice($profile->spaceId, $profile->rulesRevision);
        $mastery = $this->mastery->of(
            $slice,
            $documents['sheet'],
            $documents['choices'],
            $item->getProficiencyFamilyCode(),
            $profile->profileType,
            $documents['key'],
        );

        return $this->throwHit($request, $slice, $documents, $mastery);
    }

    /**
     * Бросок уклона: мастерство боя против сырого пула атаки.
     *
     * @param GameStrikeDodgeRequest $request Запрос уклона.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если мастерство или выбор битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function dodge(GameStrikeDodgeRequest $request): array
    {
        $slice = $this->slices->slice($request->spaceId, $request->rulesRevision);
        $mastery = $this->mastery->bestEquipped(
            $slice,
            $request->sheet,
            $request->choices,
            'strike',
            $request->participantKey,
        );
        if (!$mastery instanceof DimensionalNumber || $mastery->getBase() <= 0) {
            throw new GameInvalidException('Game strike dodge mastery is invalid');
        }

        [$efficiency, $adjustments] = $this->dodgeEfficiency(
            $slice,
            $request->profileType,
            $request->shotDistance,
        );
        $raw = is_array($request->attackerRoll['raw'] ?? null)
            ? $request->attackerRoll['raw']
            : $request->attackerRoll['roll'];

        return $this->throwDodge($request, $mastery, $efficiency, $raw, $adjustments);
    }

    /**
     * Бросает защиту выбранного предмета против фактического attacker roll.
     *
     * @param GameStrikeBlockRequest $request Запрос блока.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Результат.
     *
     * @throws GameInvalidException Если выбор или mastery невалидны.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    public function block(GameStrikeBlockRequest $request): array
    {
        $this->checks->assertBlockSelection(
            $request->spaceId,
            $request->rulesRevision,
            $request->choices,
            $request->blockItemInventoryId,
            $request->blockItemProfileIndex,
            null,
        );
        $item = $this->equipment->selectedItemById(
            $request->spaceId,
            $request->rulesRevision,
            $request->choices,
            $request->blockItemInventoryId,
        );
        $profile = $item->getBlockProfile();
        if ($profile === null) {
            throw new GameInvalidException('Game strike block profile is invalid');
        }

        return $this->throwBlock($request, $profile, $this->blockMastery($request, $item));
    }

    /**
     * Документы атакующего.
     *
     * @param GameStrikeTarget $target Запрос удара.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameNotFoundException Если листа нет.
     */
    private function documents(GameStrikeTarget $target): array
    {
        return $this->participants->attackerDocuments(
            $target->actor->kind,
            $target->actor->id,
            $target->actor->npcSheet,
            $target->actor->npcChoices,
            $target->actor->sheetVersion,
        );
    }

    /**
     * Точность проверки с учётом сдвига карточки и хвоста.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param GameStrikeHitRequest $request Запрос броска.
     * @param array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} $documents Документы.
     *
     * @return DimensionalNumber Точность.
     *
     * @throws GameInvalidException Если профиль битый.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function accuracy(CharacterRuleSlice $slice, GameStrikeHitRequest $request, array $documents): DimensionalNumber
    {
        $profile = $request->target->profile;
        $accuracy = $this->slices->profile(
            $profile->spaceId,
            $profile->rulesRevision,
            $profile->itemRuleCode,
            $profile->profileType,
            $profile->profileIndex,
        )->getAccuracy();
        $delta = $this->attackAccuracyDelta($slice, $request->actionRuleCode, $profile->profileType)
            + $request->pendingAccuracyDelta;
        if ($delta !== 0) {
            $accuracy = (new CharacteristicNumber($accuracy->getBase(), $accuracy->getSize()))->modify($delta);
        }

        return $accuracy;
    }

    /**
     * Корректировка успехов карточки и хвоста.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param GameStrikeHitRequest $request Запрос броска.
     *
     * @return RollAdjustment Корректировка.
     *
     * @throws GameInvalidException Если карточка битая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function adjustment(CharacterRuleSlice $slice, GameStrikeHitRequest $request): RollAdjustment
    {
        $adjustment = (new GameRollAdjustment())->fromCard(
            $slice,
            $request->actionRuleCode,
            $request->target->profile->profileType,
        );
        if ($request->pendingAdjustment !== null) {
            $adjustment = $adjustment->merge($request->pendingAdjustment);
        }

        return $adjustment;
    }

    /**
     * Сдвиг точности текущего удара.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Профиль: strike, throw или shoot.
     *
     * @return int Сумма дельт.
     */
    private function attackAccuracyDelta(CharacterRuleSlice $slice, string $actionRuleCode, string $profileType): int
    {
        if ($actionRuleCode === '') {
            return 0;
        }

        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return 0;
        }

        $delta = 0;
        foreach ($spec->getBase()->getActionEffects() as $effect) {
            if (!$effect instanceof CurrentActionAttackAccuracyEffect) {
                continue;
            }

            if (!$effect->getScope()->appliesTo($profileType, 1)) {
                continue;
            }

            $delta += $effect->getDelta();
        }

        return $delta;
    }

    /**
     * Эффективность уклона и её поправки от дистанции.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $profileType Тип профиля атаки.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     *
     * @return array{CharacteristicNumber, array<int, array{source_code: string, source_label: string, delta: int}>} Эффективность и поправки.
     *
     * @throws GameInvalidException Если карточка уклона битая.
     */
    private function dodgeEfficiency(
        CharacterRuleSlice $slice,
        ?string $profileType,
        ?DimensionalNumber $shotDistance,
    ): array {
        $rule = $this->dodgeRule($slice);
        $efficiency = $this->ruleEfficiency($rule);
        $penalty = $this->rangedPenalty($rule);
        if (!$penalty instanceof DodgeRangedPenalty) {
            return [$efficiency, []];
        }

        if (
            $profileType === null
            || $penalty->getProfileType() !== $profileType
            || !$shotDistance instanceof DimensionalNumber
            || $shotDistance->compare($penalty->getDistance()) < 0
        ) {
            return [$efficiency, []];
        }

        return [
            $efficiency->modify($penalty->getDelta()),
            [[
                'source_code' => 'distance',
                'source_label' => AdvantageSourceLabels::getLabel('distance'),
                'delta' => $penalty->getDelta(),
            ]],
        ];
    }

    /**
     * Карточка уклона.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return CharacterResolvedRule Карточка.
     *
     * @throws GameInvalidException Если карточки нет.
     */
    private function dodgeRule(CharacterRuleSlice $slice): CharacterResolvedRule
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof AbilityActionSpec && $spec->getBase()->getCombatAction() === 'dodge') {
                return $rule;
            }
        }

        throw new GameInvalidException('Game strike dodge rule is invalid');
    }

    /**
     * Дальнобойный штраф уклона.
     *
     * @param CharacterResolvedRule $rule Карточка.
     *
     * @return DodgeRangedPenalty|null Штраф или null.
     */
    private function rangedPenalty(CharacterResolvedRule $rule): ?DodgeRangedPenalty
    {
        $spec = $rule->getSpec();

        return $spec instanceof AbilityActionSpec ? $spec->getBase()->getDodgeRangedPenalty() : null;
    }

    /**
     * Эффективность уклона карточки.
     *
     * @param CharacterResolvedRule $rule Карточка.
     *
     * @return CharacteristicNumber Эффективность.
     *
     * @throws GameInvalidException Если эффективность битая.
     */
    private function ruleEfficiency(CharacterResolvedRule $rule): CharacteristicNumber
    {
        $spec = $rule->getSpec();
        $efficiency = $spec instanceof AbilityActionSpec ? $spec->getBase()->getDodgeEfficiency() : null;
        if (!$efficiency instanceof CharacteristicNumber) {
            throw new GameInvalidException('Game strike dodge efficiency is invalid');
        }

        return $efficiency;
    }

    /**
     * Выполняет бросок попадания.
     *
     * @param GameStrikeHitRequest $request Запрос броска.
     * @param CharacterRuleSlice $slice Срез.
     * @param array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} $documents Документы.
     * @param DimensionalNumber $mastery Мастерство.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Результат.
     *
     * @throws GameInvalidException Если точность или карточка битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function throwHit(
        GameStrikeHitRequest $request,
        CharacterRuleSlice $slice,
        array $documents,
        DimensionalNumber $mastery,
    ): array {
        $profile = $request->target->profile;

        return $this->hitRoll->throwHitWithMastery(
            $profile->spaceId,
            $profile->rulesRevision,
            $request->ruleCode,
            $documents['sheet'],
            $mastery,
            $this->accuracy($slice, $request, $documents),
            ['base' => 0, 'size' => 0],
            $request->advantages,
            $this->adjustment($slice, $request),
        );
    }

    /**
     * Выполняет бросок уклона.
     *
     * @param GameStrikeDodgeRequest $request Запрос уклона.
     * @param DimensionalNumber $mastery Мастерство.
     * @param CharacteristicNumber $efficiency Эффективность.
     * @param mixed $raw Сырой пул атаки.
     * @param array<int, array{source_code: string, source_label: string, delta: int}> $adjustments Поправки.
     *
     * @return array<string, mixed> Итог.
     */
    private function throwDodge(
        GameStrikeDodgeRequest $request,
        DimensionalNumber $mastery,
        CharacteristicNumber $efficiency,
        mixed $raw,
        array $adjustments,
    ): array {
        $result = $this->hitRoll->throwHitWithMastery(
            $request->spaceId,
            $request->rulesRevision,
            $request->ruleCode,
            $request->sheet,
            $mastery,
            $efficiency,
            $raw,
            $request->advantages,
        );
        if ($adjustments !== []) {
            $result['efficiencyAdjustments'] = $adjustments;
        }

        return $result;
    }

    /**
     * Мастерство блока.
     *
     * @param GameStrikeBlockRequest $request Запрос блока.
     * @param ItemSpec $item Предмет блока.
     *
     * @return DimensionalNumber Мастерство.
     *
     * @throws GameInvalidException Если семейство битое.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function blockMastery(GameStrikeBlockRequest $request, ItemSpec $item): DimensionalNumber
    {
        $slice = $this->slices->slice($request->spaceId, $request->rulesRevision);

        return $this->mastery->of(
            $slice,
            $request->sheet,
            $request->choices,
            $item->getProficiencyFamilyCode(),
            'strike',
            $request->participantKey,
        );
    }

    /**
     * Выполняет бросок блока.
     *
     * @param GameStrikeBlockRequest $request Запрос блока.
     * @param BlockProfile $profile Профиль блока.
     * @param DimensionalNumber $mastery Мастерство.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Результат.
     */
    private function throwBlock(
        GameStrikeBlockRequest $request,
        BlockProfile $profile,
        DimensionalNumber $mastery,
    ): array {
        return $this->hitRoll->throwHitWithMastery(
            $request->spaceId,
            $request->rulesRevision,
            $request->ruleCode,
            $request->sheet,
            $mastery,
            $profile->getEfficiency(),
            $request->attackerRoll['roll'],
            $request->advantages,
        );
    }
}
