<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameStrikeDefenseTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackDodgeSoakEffect;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Защита цели: сопротивление слоёв, смягчение реакции и его срезы.
 */
final class GameStrikeDefense
{
    private const REACTION_CHARACTERISTIC = 'reaction';

    private readonly GameStrikeAmounts $amounts;

    /**
     * Принимает срезы, участников, слои и карточки.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники удара.
     * @param GameStrikeLayers $layers Сопротивление слоёв.
     * @param GameStrikeSoaks $soaks Смягчение реакции.
     * @param GameStrikeActionCards $cards Живые карточки действий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeParticipants $participants,
        private readonly GameStrikeLayers $layers,
        private readonly GameStrikeSoaks $soaks,
        private readonly GameStrikeActionCards $cards,
    ) {
        $this->amounts = new GameStrikeAmounts();
    }

    /**
     * Сопротивление брони цели по типу урона профиля.
     *
     * @param GameStrikeDefenseTarget $target Запрос защиты.
     *
     * @return DimensionalNumber Сумма слоёв или ноль.
     *
     * @throws GameInvalidException Если профиль, тип или лист битые.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    public function resistance(GameStrikeDefenseTarget $target): DimensionalNumber
    {
        $this->assertDefender($target->defender->kind);
        $damageType = $this->damageTypeOf($target);
        if ($damageType === null) {
            return $this->amounts->zero();
        }

        $profile = $target->profile;
        $documents = $this->documents($target);

        return $this->layers->total(
            $this->slices->slice($profile->spaceId, $profile->rulesRevision),
            $damageType,
            $documents['sheet'],
            $documents['choices'],
            $target->reaction,
            $target->blockItemInventoryId,
            $target->rating,
            $target->penetration,
            $target->durabilityShave,
        );
    }

    /**
     * Смягчение реакции цели.
     *
     * @param GameStrikeDefenseTarget $target Запрос защиты.
     * @param string $actionRuleCode Карточка удара.
     * @param int $success Успех попадания.
     * @param int $reduceBy Снижение от Реакции атакующего.
     *
     * @return DimensionalNumber|null Пара после сдвига или null, если у реакции нет dodge_soak.
     *
     * @throws GameInvalidException Если характеристика или профиль битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function soak(
        GameStrikeDefenseTarget $target,
        string $actionRuleCode,
        int $success,
        int $reduceBy = 0,
    ): ?DimensionalNumber {
        $this->assertDefender($target->defender->kind);
        $profile = $target->profile;
        $slice = $this->slices->slice($profile->spaceId, $profile->rulesRevision);
        $characteristicCode = $this->dodgeSoakCode($slice, $target->reaction);
        if ($characteristicCode === null) {
            return null;
        }

        $soak = $this->baseSoak($slice, $target, $characteristicCode);

        return $this->cutSoak($slice, $actionRuleCode, $profile->profileType, $success, $soak, $reduceBy);
    }

    /**
     * Снижение смягчения от Реакции атакующего по хвосту подготовки.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKind Вид атакующего.
     * @param int $actorId Атакующий.
     * @param string $profileType Тип профиля.
     * @param int $hitNumber Номер попадания.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     * @param int|null $npcVersion Версия листа NPC.
     *
     * @return int Целое значение Реакции или 0, если хвоста нет.
     *
     * @throws GameInvalidException Если у атакующего нет Реакции.
     * @throws GameNotFoundException Если листа нет.
     */
    public function dodgeSoakReduction(
        int $spaceId,
        int $rulesRevision,
        array $pending,
        string $actorKind,
        int $actorId,
        string $profileType,
        int $hitNumber,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $npcVersion,
    ): int {
        $effect = $this->armedSoakTail($pending, $actorKind, $actorId, $profileType, $hitNumber);
        if ($effect === null) {
            return 0;
        }

        return $this->reactionValue(
            $spaceId,
            $rulesRevision,
            $actorKind,
            $actorId,
            $npcSheet,
            $npcChoices,
            $npcVersion,
        );
    }

    /**
     * Тип урона профиля защиты.
     *
     * @param GameStrikeDefenseTarget $target Запрос защиты.
     *
     * @return string|null Код или null.
     */
    private function damageTypeOf(GameStrikeDefenseTarget $target): ?string
    {
        $profile = $target->profile;
        $code = $this->slices->profile(
            $profile->spaceId,
            $profile->rulesRevision,
            $profile->itemRuleCode,
            $profile->profileType,
            $profile->profileIndex,
        )->getDamage()->getDamageTypeCode();

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Документы защитника.
     *
     * @param GameStrikeDefenseTarget $target Запрос защиты.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameNotFoundException Если листа нет.
     */
    private function documents(GameStrikeDefenseTarget $target): array
    {
        return $this->participants->defenderDocuments(
            $target->defender->kind,
            $target->defender->id,
            $target->defender->npcSheet,
            $target->defender->npcChoices,
            $target->defender->sheetVersion,
        );
    }

    /**
     * Базовое смягчение реакции до эффектов карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param GameStrikeDefenseTarget $target Запрос защиты.
     * @param string $characteristicCode Код характеристики.
     *
     * @return DimensionalNumber Смягчение.
     *
     * @throws GameInvalidException Если характеристика битая.
     * @throws GameNotFoundException Если листа нет.
     */
    private function baseSoak(
        CharacterRuleSlice $slice,
        GameStrikeDefenseTarget $target,
        string $characteristicCode,
    ): DimensionalNumber {
        $profile = $target->profile;
        $benefit = $this->slices->profile(
            $profile->spaceId,
            $profile->rulesRevision,
            $profile->itemRuleCode,
            $profile->profileType,
            $profile->profileIndex,
        )->getDodgeBenefit() ?? -3;
        $documents = $this->documents($target);

        return $this->soaks->shift(
            $this->participants->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key']),
            $characteristicCode,
            $benefit,
        );
    }

    /**
     * Проверяет вид защитника.
     *
     * @param string $kind Character или npc.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertDefender(string $kind): void
    {
        if ($kind !== 'character' && $kind !== 'npc') {
            throw new GameInvalidException('Game strike defender is invalid');
        }
    }

    /**
     * Режет смягчение эффектами карточки удара.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка удара.
     * @param string $profileType Тип профиля.
     * @param int $success Успех попадания.
     * @param DimensionalNumber $soak Базовое смягчение.
     * @param int $reduceBy Снижение от Реакции атакующего.
     *
     * @return DimensionalNumber Смягчение после эффектов.
     */
    private function cutSoak(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $success,
        DimensionalNumber $soak,
        int $reduceBy,
    ): DimensionalNumber {
        $cuts = $this->soakCuts($slice, $actionRuleCode, $profileType);
        if ($cuts['sizeDelta'] !== 0) {
            $soak = $soak->modify($cuts['sizeDelta']);
        }

        if ($cuts['ignoreAtSr'] !== null && $success >= $cuts['ignoreAtSr']) {
            return new DimensionalNumber(0, 0);
        }

        if ($reduceBy > 0) {
            return new DimensionalNumber(max(0, $soak->toInteger() - $reduceBy), 0);
        }

        return $soak;
    }

    /**
     * Суммарный сдвиг размера и порог игнора эффектов текущей атаки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка удара.
     * @param string $profileType Тип профиля.
     *
     * @return array{sizeDelta: int, ignoreAtSr: int|null} Сдвиг и порог.
     */
    private function soakCuts(CharacterRuleSlice $slice, string $actionRuleCode, string $profileType): array
    {
        $sizeDelta = 0;
        $ignoreAtSr = null;
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
        foreach ($base?->getActionEffects() ?? [] as $effect) {
            if (!$effect instanceof CurrentActionAttackDodgeSoakEffect) {
                continue;
            }

            if (!$effect->getScope()->appliesTo($profileType, 1)) {
                continue;
            }

            $sizeDelta += $effect->getSizeDelta();
            $ignore = $effect->getIgnoreAtSr();
            if ($ignore !== null) {
                $ignoreAtSr = $ignoreAtSr === null ? $ignore : min($ignoreAtSr, $ignore);
            }
        }

        return ['sizeDelta' => $sizeDelta, 'ignoreAtSr' => $ignoreAtSr];
    }

    /**
     * Код характеристики смягчения из спеки выбранной реакции.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $reaction dodge или block.
     *
     * @return string|null Код или null, если поля нет.
     */
    private function dodgeSoakCode(CharacterRuleSlice $slice, string $reaction): ?string
    {
        $ruleCode = $this->cards->reactionRuleCode($slice->getSpaceId(), $slice->getRevision(), $reaction);
        if ($ruleCode === null) {
            return null;
        }

        $spec = $slice->findLive($ruleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return null;
        }

        $code = $spec->getBase()->getDodgeSoak();

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Взведённый хвост смягчения, подходящий к попаданию.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKind Вид атакующего.
     * @param int $actorId Атакующий.
     * @param string $profileType Тип профиля.
     * @param int $hitNumber Номер попадания.
     *
     * @return array<string, mixed>|null Эффект или null.
     */
    private function armedSoakTail(
        array $pending,
        string $actorKind,
        int $actorId,
        string $profileType,
        int $hitNumber,
    ): ?array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (
                !is_array($effect)
                || ($effect['type'] ?? null) !== 'next_action_attack_dodge_soak_from_reaction'
                || ($effect['armed'] ?? false) !== true
            ) {
                continue;
            }

            if (!GamePendingActionScope::applies($effect['scope'] ?? null, $profileType, $hitNumber)) {
                continue;
            }

            return $effect;
        }

        return null;
    }

    /**
     * Целое значение Реакции атакующего.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $actorKind Вид атакующего.
     * @param int $actorId Атакующий.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     * @param int|null $npcVersion Версия листа NPC.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если у атакующего нет Реакции.
     * @throws GameNotFoundException Если листа нет.
     */
    private function reactionValue(
        int $spaceId,
        int $rulesRevision,
        string $actorKind,
        int $actorId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $npcVersion,
    ): int {
        $slice = $this->slices->slice($spaceId, $rulesRevision);
        $documents = $this->participants->attackerDocuments($actorKind, $actorId, $npcSheet, $npcChoices, $npcVersion);
        $value = $this->participants->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key'])
            ->findCharacteristic(self::REACTION_CHARACTERISTIC);
        if ($value === null) {
            throw new GameInvalidException('Game strike reaction characteristic is invalid');
        }

        return max(0, $value->toInteger());
    }
}
