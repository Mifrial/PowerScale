<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;

/**
 * Живые карточки действия: база, толчок, реакция, закрытие процесса и проверка.
 */
final class GameStrikeActionCards
{
    /**
     * Принимает срезы и участников.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeParticipants $participants,
    ) {
    }

    /**
     * Общие поля карточки действия.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     *
     * @return AbilityBase|null Поля или null.
     *
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function actionBaseOf(int $spaceId, int $rulesRevision, string $actionRuleCode): ?AbilityBase
    {
        return GameStrikeRuleLookup::actionBase($this->slices->slice($spaceId, $rulesRevision), $actionRuleCode);
    }

    /**
     * Толчок карточки действия или null.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $actionRuleCode Действие.
     *
     * @return PushSpec|null Толчок.
     *
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function pushOf(int $spaceId, int $rulesRevision, string $actionRuleCode): ?PushSpec
    {
        return $this->actionBaseOf($spaceId, $rulesRevision, $actionRuleCode)?->getPush();
    }

    /**
     * Снимает ли навык потолок экземпляров оружия.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     * @param string $actorKind Вид актора.
     * @param int $actorId Идентификатор актора.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return bool true, если снимает.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function liftsMaxWeapons(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        ?array $npcChoices,
    ): bool {
        $levels = $this->abilityLevels($actorKind, $actorId, $npcChoices);
        $slice = $this->slices->slice($spaceId, $rulesRevision);
        foreach ($slice->getLiveRules() as $rule) {
            $base = $this->childBase($rule->getSpec(), $actionRuleCode);
            if ($base === null || !$base->isLiftParentMaxWeapons()) {
                continue;
            }

            if (($levels[$rule->getCode()] ?? 0) >= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Последовательные удары: свой бросок и реакция на каждый.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     *
     * @return bool true для последовательных ударов.
     *
     * @throws GameInvalidException Если карточка битая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function isSequentialStrike(int $spaceId, int $rulesRevision, string $actionRuleCode): bool
    {
        $base = $this->actionBaseOf($spaceId, $rulesRevision, $actionRuleCode);

        return $base !== null && (($base->getStrikeCount() ?? 1) > 1 || $base->isDistinctWeapons());
    }

    /**
     * Широкая ли карточка: потолок целей больше одной.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     *
     * @return bool true для широкой атаки.
     *
     * @throws GameInvalidException Если карточка битая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function isWideAttack(int $spaceId, int $rulesRevision, string $actionRuleCode): bool
    {
        $base = $this->actionBaseOf($spaceId, $rulesRevision, $actionRuleCode);
        if ($base === null) {
            throw new GameInvalidException('Game strike action is invalid');
        }

        return ($base->getMaxTargets() ?? 1) > 1;
    }

    /**
     * Карточка реакции с нужным combat action.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $reaction Сторона dodge или block.
     *
     * @return string|null Код или null.
     */
    public function reactionRuleCode(int $spaceId, int $rulesRevision, string $reaction): ?string
    {
        foreach ($this->slices->slice($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof AbilityActionSpec && $spec->getBase()->getCombatAction() === $reaction) {
                return $rule->getCode();
            }
        }

        return null;
    }

    /**
     * Первая по коду живая карточка-потомок, закрывающая процесс.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $processCode Карточка процесса.
     * @param array<string, mixed> $counters Счётчики сессии.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return array{code: string, spec: AbilityWithBase}|null Карточка или null.
     */
    public function closingAbility(
        int $spaceId,
        int $rulesRevision,
        string $processCode,
        array $counters,
        array $abilityLevels,
    ): ?array {
        $found = $this->closingCandidates($spaceId, $rulesRevision, $processCode, $counters, $abilityLevels);
        if ($found === []) {
            return null;
        }

        ksort($found, SORT_STRING);
        $code = array_key_first($found);

        return ['code' => (string) $code, 'spec' => $found[$code]];
    }

    /**
     * Единственная живая проверка характеристики с заданной сложностью.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $characteristic Код характеристики.
     *
     * @return string Код проверки.
     *
     * @throws GameInvalidException Если проверки нет или их несколько.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function checkByCharacteristic(int $spaceId, int $rulesRevision, string $characteristic): string
    {
        $matched = [];
        foreach ($this->slices->slice($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (
                $spec instanceof CheckSpec
                && $spec->getCharacteristicCode() === $characteristic
                && $spec->getDifficulty()->getKind() === 'ask'
            ) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game push check is invalid');
        }

        return $matched[0];
    }

    /**
     * Уровни способностей актора.
     *
     * @param string $actorKind Вид актора.
     * @param int $actorId Идентификатор актора.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return array<string, int> Уровни.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function abilityLevels(string $actorKind, int $actorId, ?array $npcChoices): array
    {
        $sheet = $actorKind === 'character'
            ? $this->participants->characterSnapshot($actorId)['sheet']
            : (is_array($npcChoices['sheet'] ?? null) ? $npcChoices['sheet'] : []);

        return is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [];
    }

    /**
     * База купленного потомка карточки.
     *
     * @param mixed $spec Спека правила.
     * @param string $parentCode Код родителя.
     *
     * @return AbilityBase|null База или null.
     */
    private function childBase(mixed $spec, string $parentCode): ?AbilityBase
    {
        if (!$spec instanceof AbilityActionSpec && !$spec instanceof AbilityPlainSpec) {
            return null;
        }

        $base = $spec->getBase();

        return $base->getParentAbilityCode() === $parentCode ? $base : null;
    }

    /**
     * Кандидаты закрытия процесса.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $processCode Карточка процесса.
     * @param array<string, mixed> $counters Счётчики сессии.
     * @param array<string, int> $abilityLevels Уровни способностей.
     *
     * @return array<string, AbilityWithBase> Карточки по коду.
     */
    private function closingCandidates(
        int $spaceId,
        int $rulesRevision,
        string $processCode,
        array $counters,
        array $abilityLevels,
    ): array {
        $found = [];
        foreach ($this->slices->slice($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $this->closingSpec($rule, $processCode, $counters, $abilityLevels);
            if ($spec === null) {
                continue;
            }

            $found[$rule->getCode()] = $spec;
        }

        return $found;
    }

    /**
     * Карточка закрывает процесс в текущих условиях.
     *
     * @param CharacterResolvedRule $rule Правило.
     * @param string $processCode Карточка процесса.
     * @param array<string, mixed> $counters Счётчики сессии.
     * @param array<string, int> $abilityLevels Уровни способностей.
     *
     * @return AbilityWithBase|null Спека или null.
     */
    private function closingSpec(
        CharacterResolvedRule $rule,
        string $processCode,
        array $counters,
        array $abilityLevels,
    ): ?AbilityWithBase {
        $spec = $rule->getSpec();
        if (!$spec instanceof AbilityWithBase) {
            return null;
        }

        $base = $spec->getBase();
        $close = $base->getClosesParentProcess();
        $skip = $base->getParentAbilityCode() !== $processCode
            || $close === null
            || ($abilityLevels[$rule->getCode()] ?? 0) < 1
            || !$close->meets($counters);

        return $skip ? null : $spec;
    }
}
