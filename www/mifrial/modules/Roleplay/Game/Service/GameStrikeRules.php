<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackAccuracyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackCharacteristicModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackDodgeSoakEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionCheckModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionDurabilityShaveEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Проверка выбора удара по срезу ревизии игры.
 */
final class GameStrikeRules
{
    private const REACTION_CHARACTERISTIC = 'reaction';

    private readonly GameStrikeSoaks $soaks;

    private readonly GameStrikeAmounts $amounts;

    private readonly GameStrikeSplits $splits;

    private readonly GameActionResourceResolver $resourceResolver;

    private readonly GameStrikeSuccessModifier $successModifier;

    /**
     * @var array<string, CharacterRuleSlice>
     */
    private array $ruleSliceCache = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sheetCache = [];

    /**
     * @var array<string, FormulaContext>
     */
    private array $contextCache = [];

    /**
     * Создаёт проверку.
     *
     * @param ICharacterRuleSlices $ruleSlices Срез.
     * @param ICharacters $characters Лист.
     * @param ICharacterFormulaContexts $formulaContexts Контекст листа.
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     * @param GameCheckRoll $hitRoll Бросок попадания.
     * @param GameStrikeLayers $layers Сопротивление слоёв.
     * @param ICharacterSheets $sheets Полный пересчёт листа.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly ICharacters $characters,
        private readonly ICharacterFormulaContexts $formulaContexts,
        private readonly IFormulaEvaluations $formulaEvaluations,
        private readonly GameCheckRoll $hitRoll,
        private readonly GameStrikeLayers $layers,
        private readonly ICharacterSheets $sheets,
    ) {
        $this->soaks = new GameStrikeSoaks();
        $this->amounts = new GameStrikeAmounts();
        $this->splits = new GameStrikeSplits();
        $this->resourceResolver = new GameActionResourceResolver();
        $this->successModifier = new GameStrikeSuccessModifier();
    }

    /**
     * Разрешает action resource spends по live Rule.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $actionRuleCode Код действия.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Код предмета.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param array<string, mixed> $sheet Authoritative sheet.
     * @param array<string, mixed> $chosenAmounts Server-owned choices.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     *
     * @return array<int, ResourceSpend> Native spends.
     *
     * @throws GameInvalidException If action or resource is invalid.
     * @throws GameNotFoundException If revision is absent.
     */
    public function resolveResourceSpends(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        int $itemInventoryId,
        string $itemRuleCode,
        array $choices,
        array $sheet,
        array $chosenAmounts = [],
        bool $allowZeroChosen = false,
    ): array {
        return $this->resourceResolver->resolve(
            $this->slice($spaceId, $rulesRevision),
            $actionRuleCode,
            $itemInventoryId,
            $itemRuleCode,
            $choices,
            $sheet,
            $chosenAmounts,
            $allowZeroChosen,
        );
    }

    /**
     * Разрешает resource spends выбранной reaction action.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $reaction Reaction semantic.
     * @param int|null $itemInventoryId Строка предмета блока.
     * @param string $itemRuleCode Код предмета блока.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param array<string, mixed> $sheet Authoritative sheet.
     * @param array<string, mixed> $chosenAmounts Server-owned choices.
     *
     * @return array<int, ResourceSpend> Native spends.
     *
     * @throws GameInvalidException If action is missing or ambiguous.
     * @throws GameNotFoundException If revision is absent.
     */
    public function resolveReactionResourceSpends(
        int $spaceId,
        int $rulesRevision,
        string $reaction,
        ?int $itemInventoryId,
        string $itemRuleCode,
        array $choices,
        array $sheet,
        array $chosenAmounts = [],
    ): array {
        return $this->resourceResolver->resolveReaction(
            $this->slice($spaceId, $rulesRevision),
            $reaction,
            $itemInventoryId,
            $itemRuleCode,
            $choices,
            $sheet,
            $chosenAmounts,
        );
    }

    /**
     * Проверяет current по уже resolved spends.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $sheet Authoritative sheet.
     * @param array<int, ResourceSpend> $spends Native spends.
     *
     * @return bool true, если current достаточен.
     *
     * @throws GameInvalidException If resources are invalid.
     * @throws GameNotFoundException If revision is absent.
     */
    public function hasSufficientResources(
        int $spaceId,
        int $rulesRevision,
        array $sheet,
        array $spends,
    ): bool {
        return $this->resourceResolver->hasSufficientCurrent(
            $this->slice($spaceId, $rulesRevision),
            $sheet,
            $spends,
        );
    }

    /**
     * Живые коды и профиль оружия.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     *
     * @return void
     *
     * @throws GameInvalidException Если правила или профиля нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertAttack(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        int $itemInventoryId,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        string $attackerKind,
        int $attackerId,
        ?array $npcChoices,
    ): void {
        $this->assertLive($spaceId, $rulesRevision, $actionRuleCode);
        $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex);
        $choices = $attackerKind === 'character'
            ? $this->characterSnapshot($attackerId)['choices']
            : $npcChoices;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike attacker choices are invalid');
        }

        $this->assertInventoryItem($choices, $itemInventoryId, $itemRuleCode, $spaceId, $rulesRevision);
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
        $base = $this->actionBase($this->slice($spaceId, $rulesRevision), $actionRuleCode);
        if ($base === null) {
            throw new GameInvalidException('Game strike action is invalid');
        }

        return ($base->getMaxTargets() ?? 1) > 1;
    }

    /**
     * Проверяет число целей карточки: потолок `max_targets` и режим.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     * @param int $targetCount Число целей.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточка битая или число целей вне потолка.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertAttackTargets(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        int $targetCount,
    ): void {
        $base = $this->actionBase($this->slice($spaceId, $rulesRevision), $actionRuleCode);
        if ($base === null) {
            throw new GameInvalidException('Game strike action is invalid');
        }

        $maxTargets = $base->getMaxTargets() ?? 1;
        if ($maxTargets < 1) {
            throw new GameInvalidException('Game strike max targets is invalid');
        }

        $mode = $base->getAttackMode();
        if ($mode !== null && ($mode === 'wide') !== ($maxTargets > 1)) {
            throw new GameInvalidException('Game strike attack mode is invalid');
        }

        if ($targetCount < 1 || $targetCount > $maxTargets) {
            throw new GameInvalidException('Game strike target count is invalid');
        }
    }

    /**
     * Помеха за число целей широкой атаки.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     * @param int $targetCount Число целей.
     *
     * @return RollAdvantage|null Запись или null.
     *
     * @throws GameInvalidException Если карточка битая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function wideTargetCheckAdvantage(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        int $targetCount,
    ): ?RollAdvantage {
        $base = $this->actionBase($this->slice($spaceId, $rulesRevision), $actionRuleCode);
        $delta = $base?->getWideTargetCheckAdvantage() ?? 0;
        if ($delta === 0 || $targetCount < 2) {
            return null;
        }

        return new RollAdvantage('circumstances', $delta * ($targetCount - 1));
    }

    /**
     * Разрешает списание выбранного предмета или боеприпаса.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Код атакующего предмета.
     * @param string $profileType Тип профиля.
     * @param int $profileIndex Индекс профиля.
     * @param int $itemInventoryId Строка предмета.
     * @param int|null $ammunitionInventoryId Строка боеприпаса.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     *
     * @return array{kind: string, inventoryId: int, ruleCode: string}|null Списание или null.
     *
     * @throws GameInvalidException Если профиль, предмет или боеприпас невалидны.
     * @throws GameNotFoundException Если ревизия или персонаж отсутствуют.
     */
    public function resolveInventorySpend(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $itemInventoryId,
        ?int $ammunitionInventoryId,
        string $attackerKind,
        int $attackerId,
        ?array $npcChoices,
    ): ?array {
        $choices = $attackerKind === 'character'
            ? $this->characterSnapshot($attackerId)['choices']
            : $npcChoices;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike attacker choices are invalid');
        }

        $item = $this->selectedItem($spaceId, $rulesRevision, $choices, $itemInventoryId, $itemRuleCode);
        $weapon = $item->getWeapon();
        $profiles = $weapon?->getWeaponProfiles() ?? [];
        $profile = $profiles[$profileIndex] ?? null;
        if (!$profile instanceof WeaponProfile || $profile->getType() !== $profileType) {
            throw new GameInvalidException('Game strike profile is invalid');
        }

        if ($profileType === 'strike') {
            if ($ammunitionInventoryId !== null) {
                throw new GameInvalidException('Game strike ammunition is unexpected');
            }

            return null;
        }

        if ($profileType === 'throw') {
            if ($ammunitionInventoryId !== null) {
                throw new GameInvalidException('Game throw ammunition is unexpected');
            }

            $this->assertPositiveQuantity($choices, $itemInventoryId);

            return [
                'kind' => 'decrementInventoryQuantity',
                'inventoryId' => $itemInventoryId,
                'ruleCode' => $itemRuleCode,
            ];
        }

        if ($profileType !== 'shoot' || !is_int($ammunitionInventoryId)) {
            throw new GameInvalidException('Game strike ammunition is invalid');
        }

        $requirements = $weapon?->getAmmunitionRequirements() ?? [];
        if ($requirements === []) {
            throw new GameInvalidException('Game shoot ammunition requirements are missing');
        }

        $ammunition = $this->selectedAmmunition($choices, $ammunitionInventoryId);
        $ammunitionRuleCode = $ammunition['ruleCode'];
        $ammunitionRule = $this->slice($spaceId, $rulesRevision)->findLive($ammunitionRuleCode);
        $ammunitionSpec = $ammunitionRule?->getSpec();
        if (
            $ammunitionRule === null
            || $ammunitionRule->isSpecBroken()
            || !$ammunitionSpec instanceof ItemSpec
        ) {
            throw new GameInvalidException('Game ammunition item is invalid');
        }

        $keywords = $ammunitionRule->getKeywordCodes();
        foreach ($requirements as $requirement) {
            if (array_diff($requirement, $keywords) === []) {
                return [
                    'kind' => 'decrementInventoryQuantity',
                    'inventoryId' => $ammunitionInventoryId,
                    'ruleCode' => $ammunitionRuleCode,
                ];
            }
        }

        throw new GameInvalidException('Game ammunition is incompatible');
    }

    /**
     * Число урона профиля для атакующего.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $attackerKind Character или npc.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC или null.
     * @param int|null $sheetVersion Версия листа NPC.
     * @param int|null $actionCharacteristicModifier Сдвиг характеристики действия.
     *
     * @return DimensionalNumber Пара формулы.
     *
     * @throws GameInvalidException Если профиль, лист или формула битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function evaluateDamage(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $itemInventoryId,
        string $attackerKind,
        int $attackerId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
        ?int $actionCharacteristicModifier = null,
    ): DimensionalNumber {
        if ($attackerKind !== 'character' && $attackerKind !== 'npc') {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        try {
            $documents = $this->attackerDocuments($attackerKind, $attackerId, $npcSheet, $npcChoices, $sheetVersion);
            $item = $this->selectedItem(
                $spaceId,
                $rulesRevision,
                $documents['choices'],
                $itemInventoryId,
                $itemRuleCode,
            );
            $weapon = $item->getWeapon();
            $profiles = $weapon?->getWeaponProfiles() ?? [];
            $profile = $profiles[$profileIndex] ?? null;
            if (!$profile instanceof WeaponProfile || $profile->getType() !== $profileType) {
                throw new GameInvalidException('Game strike damage profile is invalid');
            }

            $context = $this->formulaContext(
                $this->slice($spaceId, $rulesRevision),
                $documents['sheet'],
                $documents['choices'],
                $documents['key'],
            );
            if ($actionCharacteristicModifier !== null && $actionCharacteristicModifier !== 0) {
                $context = $context->withActionCharacteristicModifier($actionCharacteristicModifier);
            }

            return $this->formulaEvaluations->evaluateDimensional(
                $profile->getDamage()->getFormula(),
                $context,
            );
        } catch (GameInvalidException $exception) {
            throw $exception;
        } catch (RuleInvalidException | CharacterInvalidException | CharacterNotFoundException $exception) {
            throw new GameInvalidException('Game strike damage is invalid', $exception);
        }
    }

    /**
     * Вычисляет authoritative penetration выбранного inventory instance.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Код предмета для consistency check.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Индекс профиля.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $attackerKind Character или npc.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return DimensionalNumber Размерное проникновение.
     *
     * @throws GameInvalidException Если instance, профиль или формула битые.
     * @throws GameNotFoundException Если ревизия или персонаж отсутствуют.
     */
    public function evaluatePenetration(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $itemInventoryId,
        string $attackerKind,
        int $attackerId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
    ): DimensionalNumber {
        $documents = $this->attackerDocuments($attackerKind, $attackerId, $npcSheet, $npcChoices, $sheetVersion);
        $item = $this->selectedItem(
            $spaceId,
            $rulesRevision,
            $documents['choices'],
            $itemInventoryId,
            $itemRuleCode,
        );
        $weapon = $item->getWeapon();
        $profiles = $weapon === null ? [] : $weapon->getWeaponProfiles();
        $profile = $profiles[$profileIndex] ?? null;
        if (!$profile instanceof WeaponProfile || $profile->getType() !== $profileType) {
            throw new GameInvalidException('Game strike penetration profile is invalid');
        }

        try {
            $context = $this->formulaContext(
                $this->slice($spaceId, $rulesRevision),
                $documents['sheet'],
                $documents['choices'],
                $documents['key'],
            );

            return $this->formulaEvaluations->evaluateDimensional(
                $profile->getPenetration(),
                $context,
            );
        } catch (RuleInvalidException | CharacterInvalidException | CharacterNotFoundException $exception) {
            throw new GameInvalidException('Game strike penetration is invalid', $exception);
        }
    }

    /**
     * Сопротивление брони цели по типу урона профиля. В лист не пишется.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param string $defenderKind Character или npc.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
     * @param array<string, mixed>|null $npcChoices Документ choices NPC или null.
     * @param string $reaction Реакция.
     * @param int|null $blockItemInventoryId Строка предмета блока.
     * @param int $rating Рейтинг попадания.
     * @param DimensionalNumber|null $penetration Проникновение атакующего.
     * @param int $durabilityShave Срез надёжности с единиц удара.
     *
     * @return DimensionalNumber Сумма слоёв или ноль.
     *
     * @throws GameInvalidException Если профиль, тип или лист битые.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    public function evaluateResistance(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
        string $reaction,
        ?int $blockItemInventoryId,
        int $rating,
        ?DimensionalNumber $penetration = null,
        int $durabilityShave = 0,
    ): DimensionalNumber {
        if ($defenderKind !== 'character' && $defenderKind !== 'npc') {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $damageType = $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
            ->getDamage()
            ->getDamageTypeCode();
        if (!is_string($damageType) || $damageType === '') {
            return $this->amounts->zero();
        }

        $documents = $this->defenderDocuments($defenderKind, $defenderId, $npcSheet, $npcChoices);

        return $this->layers->total(
            $this->slice($spaceId, $rulesRevision),
            $damageType,
            $documents['sheet'],
            $documents['choices'],
            $reaction,
            $blockItemInventoryId,
            $rating,
            $penetration,
            $durabilityShave,
        );
    }

    /**
     * Смягчение реакции цели. В лист не пишется.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param string $defenderKind Character или npc.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
     * @param array<string, mixed>|null $npcChoices Выборы NPC или null.
     * @param int|null $sheetVersion Версия листа NPC.
     * @param string $reaction Выбранная реакция защиты.
     * @param string $actionRuleCode Карточка удара.
     * @param int $success Успех попадания.
     * @param int $reduceBy Снижение от Реакции атакующего.
     *
     * @return DimensionalNumber|null Пара после сдвига или null, если у реакции нет dodge_soak.
     *
     * @throws GameInvalidException Если характеристика или профиль битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function evaluateSoak(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion,
        string $reaction,
        string $actionRuleCode,
        int $success,
        int $reduceBy = 0,
    ): ?DimensionalNumber {
        if ($defenderKind !== 'character' && $defenderKind !== 'npc') {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $slice = $this->slice($spaceId, $rulesRevision);
        $characteristicCode = $this->dodgeSoakCode($slice, $reaction);
        if ($characteristicCode === null) {
            return null;
        }

        $benefit = $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
            ->getDodgeBenefit() ?? -3;
        $documents = $this->defenderDocuments($defenderKind, $defenderId, $npcSheet, $npcChoices, $sheetVersion);
        $soak = $this->soaks->shift(
            $this->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key']),
            $characteristicCode,
            $benefit,
        );

        return $this->cutSoak($slice, $actionRuleCode, $profileType, $success, $soak, $reduceBy);
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
     * Код единственной живой проверки попадания.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function findHitCode(int $spaceId, int $rulesRevision): string
    {
        return $this->hitRoll->findHitCode($spaceId, $rulesRevision);
    }

    /**
     * Проверяет auto-fail размерного броска попадания.
     *
     * @param array{base: int, size: int} $roll Размерный бросок.
     *
     * @return bool true, если достигнут минимум боевой проверки.
     */
    public function isHitAutoFail(array $roll): bool
    {
        return $this->hitRoll->isCombatAutoFail($roll);
    }

    /**
     * Бросает попадание с мастерством выбранного предмета.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код hit-check.
     * @param string $itemRuleCode Код предмета.
     * @param string $profileType Тип профиля.
     * @param int $profileIndex Индекс профиля.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $attackerKind Вид участника.
     * @param int $attackerId Участник.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     * @param string $actionRuleCode Карточка атаки. Пусто, если сдвига точности нет.
     * @param int|null $sheetVersion Версия листа NPC.
     * @param array<int, RollAdvantage> $advantages Преимущества проверки.
     * @param int $pendingAccuracyDelta Точность из хвоста прошлого действия.
     * @param RollAdjustment|null $pendingAdjustment Успехи из хвоста прошлого действия.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Результат.
     *
     * @throws GameInvalidException Если документ или mastery невалидны.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    public function rollHit(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $itemInventoryId,
        string $attackerKind,
        int $attackerId,
        ?array $npcSheet,
        ?array $npcChoices,
        string $actionRuleCode = '',
        ?int $sheetVersion = null,
        array $advantages = [],
        int $pendingAccuracyDelta = 0,
        ?RollAdjustment $pendingAdjustment = null,
    ): array {
        $documents = $this->attackerDocuments($attackerKind, $attackerId, $npcSheet, $npcChoices, $sheetVersion);
        $item = $this->selectedItem($spaceId, $rulesRevision, $documents['choices'], $itemInventoryId, $itemRuleCode);
        $mastery = $this->mastery(
            $this->slice($spaceId, $rulesRevision),
            $documents['sheet'],
            $documents['choices'],
            $item->getProficiencyFamilyCode(),
            $profileType,
            $documents['key'],
        );
        $accuracy = $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)->getAccuracy();
        $accuracyDelta = $this->attackAccuracyDelta(
            $this->slice($spaceId, $rulesRevision),
            $actionRuleCode,
            $profileType,
        ) + $pendingAccuracyDelta;
        if ($accuracyDelta !== 0) {
            $accuracy = (new CharacteristicNumber($accuracy->getBase(), $accuracy->getSize()))->modify($accuracyDelta);
        }

        $adjustment = (new GameRollAdjustment())->fromCard(
            $this->slice($spaceId, $rulesRevision),
            $actionRuleCode,
            $profileType,
        );
        if ($pendingAdjustment !== null) {
            $adjustment = $adjustment->merge($pendingAdjustment);
        }

        return $this->hitRoll->throwHitWithMastery(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            $documents['sheet'],
            $mastery,
            $accuracy,
            ['base' => 0, 'size' => 0],
            $advantages,
            $adjustment,
        );
    }

    /**
     * Бросок уклона: мастерство ближнего боя против сырого пула атаки.
     *
     * @param array<string, mixed> $sheet Лист защитника.
     * @param array<string, mixed> $choices Выборы защитника.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     *
     * @return array<string, mixed> Итог.
     */
    public function isUnconscious(int $spaceId, int $rulesRevision, array $sheet): bool
    {
        $slice = $this->slice($spaceId, $rulesRevision);
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return false;
        }

        foreach ($states as $row) {
            if (!is_array($row) || !is_string($row['stateRuleCode'] ?? null)) {
                continue;
            }

            $rule = $slice->findLive($row['stateRuleCode']);
            $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $spec->isDeclineUnconscious()) {
                return true;
            }
        }

        return false;
    }

    public function rollDodge(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        array $sheet,
        array $choices,
        string $profileType,
        array $attackerRoll,
        ?string $participantKey = null,
        array $advantages = [],
    ): array {
        $slice = $this->slice($spaceId, $rulesRevision);
        $mastery = $this->bestEquippedMastery($slice, $sheet, $choices, $profileType, $participantKey);
        if (!$mastery instanceof DimensionalNumber || $mastery->getBase() <= 0) {
            throw new GameInvalidException('Game strike dodge mastery is invalid');
        }

        $raw = is_array($attackerRoll['raw'] ?? null) ? $attackerRoll['raw'] : $attackerRoll['roll'];

        return $this->hitRoll->throwHitWithMastery(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            $sheet,
            $mastery,
            $this->dodgeEfficiency($this->dodgeRule($slice)),
            $raw,
            $advantages,
        );
    }

    /**
     * Переоценивает атаку против сырого пула уклона.
     *
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $defenderRoll Бросок уклона.
     *
     * @return array<string, mixed> Бросок атаки.
     */
    public function rerateHit(array $attackerRoll, array $defenderRoll): array
    {
        $clamped = is_array($defenderRoll['roll'] ?? null) ? $defenderRoll['roll'] : $defenderRoll['raw'];

        return $this->hitRoll->rerate($attackerRoll, $clamped);
    }

    /**
     * Бросает защиту выбранного предмета против фактического attacker roll.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код hit-check.
     * @param array<string, mixed> $sheet Лист защитника.
     * @param array<string, mixed> $choices Выборы защитника.
     * @param int $blockItemInventoryId Строка блока.
     * @param int $blockItemProfileIndex Профиль блока.
     * @param array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} $attackerRoll Результат атаки.
     * @param string|null $participantKey Ключ листа защитника.
     * @param array<int, RollAdvantage> $advantages Преимущества проверки.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Результат.
     *
     * @throws GameInvalidException Если выбор или mastery невалидны.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    public function rollBlock(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        array $sheet,
        array $choices,
        int $blockItemInventoryId,
        int $blockItemProfileIndex,
        array $attackerRoll,
        ?string $participantKey = null,
        array $advantages = [],
    ): array {
        $this->assertBlockSelection(
            $spaceId,
            $rulesRevision,
            $choices,
            $blockItemInventoryId,
            $blockItemProfileIndex,
            null,
        );
        $item = $this->selectedItemById($spaceId, $rulesRevision, $choices, $blockItemInventoryId);
        $profile = $item->getBlockProfile();
        if ($profile === null) {
            throw new GameInvalidException('Game strike block profile is invalid');
        }

        $mastery = $this->mastery(
            $this->slice($spaceId, $rulesRevision),
            $sheet,
            $choices,
            $item->getProficiencyFamilyCode(),
            'strike',
            $participantKey,
        );

        return $this->hitRoll->throwHitWithMastery(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            $sheet,
            $mastery,
            $profile->getEfficiency(),
            $attackerRoll['roll'],
            $advantages,
        );
    }

    /**
     * Текущие проверочные модификаторы карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $checkCode Код проверки.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function actionCheckAdvantages(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $checkCode,
    ): array {
        $base = $this->actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof CurrentActionCheckModifierEffect) {
                continue;
            }

            if (!in_array($checkCode, $effect->getCheckCodes(), true)) {
                continue;
            }

            $source = $effect->getSourceCode();
            $rows[] = new RollAdvantage(
                is_string($source) && $source !== '' ? $source : 'circumstances',
                $effect->getDelta(),
            );
        }

        return $rows;
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
        foreach ($this->slice($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof AbilityActionSpec && $spec->getBase()->getCombatAction() === $reaction) {
                return $rule->getCode();
            }
        }

        return null;
    }

    /**
     * Текущий сдвиг характеристики атаки карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     *
     * @return int Сдвиг.
     */
    public function actionCharacteristicModifier(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
    ): int {
        $base = $this->actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return 0;
        }

        $delta = 0;
        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof CurrentActionAttackCharacteristicModifierEffect) {
                continue;
            }

            if (!$effect->getScope()->appliesTo($profileType, $hitNumber)) {
                continue;
            }

            $delta += $effect->getDelta();
        }

        return $delta;
    }

    /**
     * Сдвиг характеристики урона от рейтинга успеха карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param int $success Рейтинг успеха.
     *
     * @return int Сдвиг.
     */
    public function successRatingCharacteristicModifier(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        int $success,
    ): int {
        $base = $this->actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return 0;
        }

        return $this->successModifier->fromEffects(
            $base->getActionEffects(),
            $profileType,
            $hitNumber,
            $success,
        );
    }

    /**
     * Срез надёжности доспеха с единиц броска попадания.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $actionRuleCode Карточка удара.
     * @param string $itemRuleCode Оружие удара.
     * @param string $profileType Тип профиля.
     * @param int $profileIndex Смещение.
     * @param array<string, mixed> $attackerRoll Бросок попадания.
     * @param int $success Успех попадания.
     * @param int $hitNumber Номер попадания.
     *
     * @return int Срез или 0.
     *
     * @throws GameInvalidException Если профиль или карточка битые.
     */
    public function durabilityShave(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        array $attackerRoll,
        int $success,
        int $hitNumber = 1,
    ): int {
        if ($success <= 0 || $actionRuleCode === '') {
            return 0;
        }

        $slice = $this->slice($spaceId, $rulesRevision);
        $damageType = $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
            ->getDamage()
            ->getDamageTypeCode();
        $shortExtra = $this->shaveShortExtra($slice, $actionRuleCode, $profileType, $damageType, $hitNumber);
        $oneCount = $shortExtra === null ? 0 : $this->oneCount($attackerRoll);
        if ($oneCount <= 0) {
            return 0;
        }

        $keywords = $slice->findLive($itemRuleCode)?->getKeywordCodes() ?? [];

        return $oneCount + ($shortExtra && in_array('short', $keywords, true) ? 1 : 0);
    }

    /**
     * Флаг короткого лишнего из эффекта среза, подходящего к типу урона.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка удара.
     * @param string $profileType Тип профиля.
     * @param string|null $damageType Тип урона профиля.
     * @param int $hitNumber Номер попадания.
     *
     * @return bool|null true или false при найденном эффекте; null, если эффекта нет.
     */
    private function shaveShortExtra(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        ?string $damageType,
        int $hitNumber,
    ): ?bool {
        $base = $this->actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return null;
        }

        $shortExtra = null;
        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof CurrentActionDurabilityShaveEffect) {
                continue;
            }

            if (!$this->shaveAppliesTo($effect, $profileType, $damageType, $hitNumber)) {
                continue;
            }

            $shortExtra = ($shortExtra ?? false) || $effect->isShortExtraOnFirstOne();
        }

        return $shortExtra;
    }

    /**
     * Эффект среза накрывает компонент, попадание и тип урона.
     *
     * @param CurrentActionDurabilityShaveEffect $effect Эффект.
     * @param string $profileType Тип профиля.
     * @param string|null $damageType Тип урона.
     * @param int $hitNumber Номер попадания.
     *
     * @return bool true, если накрывает.
     */
    private function shaveAppliesTo(
        CurrentActionDurabilityShaveEffect $effect,
        string $profileType,
        ?string $damageType,
        int $hitNumber,
    ): bool {
        if (!$effect->getScope()->appliesTo($profileType, $hitNumber)) {
            return false;
        }

        $types = $effect->getDamageTypeCodes();

        return $types === [] || (is_string($damageType) && in_array($damageType, $types, true));
    }

    /**
     * Число единиц на итоговых гранях броска.
     *
     * @param array<string, mixed> $roll Бросок.
     *
     * @return int Единицы.
     */
    private function oneCount(array $roll): int
    {
        $faces = is_array($roll['adjustedRolls'] ?? null) ? $roll['adjustedRolls'] : [];
        if ($faces === []) {
            $faces = is_array($roll['faces'] ?? null) ? $roll['faces'] : [];
        }

        $count = 0;
        foreach ($faces as $face) {
            if ($face === 1) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * База карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return AbilityBase|null База или null.
     */
    private function actionBase(CharacterRuleSlice $slice, string $actionRuleCode): ?AbilityBase
    {
        $spec = $slice->findLive($actionRuleCode)?->getSpec();

        return $spec instanceof AbilityActionSpec ? $spec->getBase() : null;
    }

    /**
     * Предмет блока — живой предмет со щитом или оружием.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $blockItemRuleCode Код.
     *
     * @return void
     *
     * @throws GameInvalidException Если предмета блока нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertBlock(int $spaceId, int $rulesRevision, string $blockItemRuleCode): void
    {
        $item = $this->item($spaceId, $rulesRevision, $blockItemRuleCode);
        if ($item->getWeapon() === null && $item->getShield() === null) {
            throw new GameInvalidException('Game strike block item is invalid');
        }
    }

    /**
     * Проверяет выбранную строку и профиль блока.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы защитника.
     * @param int $blockItemInventoryId Строка инвентаря.
     * @param int $blockItemProfileIndex Индекс профиля.
     * @param string|null $blockItemRuleCode Код для проверки согласованности.
     *
     * @return void
     *
     * @throws GameInvalidException Если выбор невалиден.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    public function assertBlockSelection(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $blockItemInventoryId,
        int $blockItemProfileIndex,
        ?string $blockItemRuleCode,
    ): void {
        if ($blockItemProfileIndex !== 0) {
            throw new GameInvalidException('Game strike block profile is invalid');
        }

        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game strike inventory is invalid');
        }

        $row = null;
        foreach ($inventory as $candidate) {
            if (is_array($candidate) && ($candidate['id'] ?? null) === $blockItemInventoryId) {
                if ($row !== null) {
                    throw new GameInvalidException('Game strike block item is ambiguous');
                }

                $row = $candidate;
            }
        }

        $ruleCode = is_array($row) ? ($row['ruleCode'] ?? null) : null;
        if (
            !is_array($row)
            || ($row['equipped'] ?? false) !== true
            || !is_string($ruleCode)
        ) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        $item = $this->item($spaceId, $rulesRevision, $ruleCode);
        if ($item->getBlockProfile() === null) {
            throw new GameInvalidException('Game strike block profile is invalid');
        }
    }

    /**
     * Возвращает authoritative rule code выбранного блока.
     *
     * @param array<string, mixed> $choices Выборы защитника.
     * @param int $blockItemInventoryId Строка блока.
     *
     * @return string Код live item.
     *
     * @throws GameInvalidException Если строка отсутствует.
     */
    public function getBlockItemRuleCode(array $choices, int $blockItemInventoryId): string
    {
        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game strike inventory is invalid');
        }

        foreach ($inventory as $row) {
            if (
                is_array($row)
                && ($row['id'] ?? null) === $blockItemInventoryId
                && ($row['equipped'] ?? false) === true
                && is_string($row['ruleCode'] ?? null)
            ) {
                return $row['ruleCode'];
            }
        }

        throw new GameInvalidException('Game strike block item is invalid');
    }

    /**
     * Срез ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return CharacterRuleSlice Срез.
     *
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function ruleSlice(int $spaceId, int $rulesRevision): CharacterRuleSlice
    {
        return $this->slice($spaceId, $rulesRevision);
    }

    /**
     * Свежий счётчик и лист персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return array{actualVersion: int, choices: array<string, mixed>, sheet: array<string, mixed>} Снимок.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    public function characterSnapshot(int $characterId): array
    {
        try {
            $record = $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }

        return [
            'actualVersion' => $record->getActualVersion(),
            'choices' => $record->getChoices(),
            'sheet' => $this->liveSheet($record),
        ];
    }

    /**
     * Ключ кэша листа участника.
     *
     * @param string $kind character или npc.
     * @param int $id Идентификатор.
     * @param int $version actualVersion строки.
     *
     * @return string Ключ.
     */
    public function makeParticipantKey(string $kind, int $id, int $version): string
    {
        return $kind . ':' . $id . ':' . $version;
    }

    /**
     * Имя персонажа для токена чата.
     */
    public function characterName(int $characterId): string
    {
        return $this->characters->get($characterId)->getName();
    }

    /**
     * Операция деления повреждения на стойкость. Лист не пишет.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $defenderKind Character или npc.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
     * @param DimensionalNumber $injury Повреждение.
     * @param array<string, mixed>|null $npcChoices Выборы NPC или null.
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return array{kind: string, remainder: array{base: int, size: int}, quotient: int} Операция.
     *
     * @throws GameInvalidException Если карточка, закупка, деление или лист битые.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    public function splitInjury(
        int $spaceId,
        int $rulesRevision,
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        DimensionalNumber $injury,
        ?array $npcChoices = null,
        ?int $sheetVersion = null,
    ): array {
        if ($defenderKind !== 'character' && $defenderKind !== 'npc') {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $slice = $this->slice($spaceId, $rulesRevision);
        $documents = $this->defenderDocuments($defenderKind, $defenderId, $npcSheet, $npcChoices, $sheetVersion);

        return $this->splits->operation(
            $slice,
            $this->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key']),
            $injury,
        );
    }

    /**
     * Стойкость цели в том же контексте, что и деление урона.
     */
    public function enduranceOf(
        int $spaceId,
        int $rulesRevision,
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
    ): DimensionalNumber {
        $slice = $this->slice($spaceId, $rulesRevision);
        $documents = $this->defenderDocuments($defenderKind, $defenderId, $npcSheet, $npcChoices, $sheetVersion);
        $value = $this->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key'])
            ->findCharacteristic($this->enduranceCode($slice));
        if (!$value instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $value;
    }

    /**
     * Профиль предмета.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид.
     * @param int $profileIndex Смещение.
     *
     * @return WeaponProfile Профиль.
     *
     * @throws GameInvalidException Если профиля нет.
     * @throws GameNotFoundException Если ревизии нет.
     */

    /**
     * Код типа урона профиля или null.
     *
     * @return string|null Код.
     */
    public function damageTypeCode(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): ?string {
        $code = $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
            ->getDamage()
            ->getDamageTypeCode();

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function dodgeRule(CharacterRuleSlice $slice): CharacterResolvedRule
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof AbilityActionSpec && $spec->getBase()->getCombatAction() === 'dodge') {
                return $rule;
            }
        }

        throw new GameInvalidException('Game strike dodge efficiency is invalid');
    }

    private function dodgeEfficiency(CharacterResolvedRule $rule): DimensionalNumber
    {
        $spec = $rule->getSpec();
        $efficiency = $spec instanceof AbilityActionSpec ? $spec->getBase()->getDodgeEfficiency() : null;
        if (!$efficiency instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike dodge efficiency is invalid');
        }

        return $efficiency;
    }

    private function combatCharacteristic(CharacterRuleSlice $slice, string $profileType): string
    {
        $matched = null;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && in_array($profileType, $spec->getWeaponMasteryProfiles(), true)) {
                if ($matched !== null) {
                    throw new GameInvalidException('Game strike characteristic is ambiguous');
                }

                $matched = $rule->getCode();
            }
        }

        if ($matched === null) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        return $matched;
    }

    private function enduranceCode(CharacterRuleSlice $slice): string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && $spec->isDamageEndurance()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $matched[0];
    }

    private function profile(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): WeaponProfile {
        $weapon = $this->item($spaceId, $rulesRevision, $itemRuleCode)->getWeapon();
        $profiles = $weapon === null ? [] : $weapon->getWeaponProfiles();
        $profile = $profiles[$profileIndex] ?? null;
        if (!$profile instanceof WeaponProfile || $profile->getType() !== $profileType) {
            throw new GameInvalidException('Game strike profile is invalid');
        }

        return $profile;
    }

    /**
     * Возвращает документы атакующего.
     *
     * @param string $kind Вид участника.
     * @param int $id Идентификатор.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     *
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameInvalidException Если NPC choices отсутствуют.
     * @throws GameNotFoundException Если персонаж отсутствует.
     */
    private function attackerDocuments(
        string $kind,
        int $id,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
    ): array {
        if ($kind === 'character') {
            $snapshot = $this->characterSnapshot($id);

            return [
                'sheet' => $snapshot['sheet'],
                'choices' => $snapshot['choices'],
                'key' => $this->makeParticipantKey('character', $id, $snapshot['actualVersion']),
            ];
        }

        if ($kind !== 'npc' || !is_array($npcSheet)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $choices = $npcChoices;
        $sheet = $npcSheet;
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game strike npc choices are missing');
        }

        return [
            'sheet' => $sheet,
            'choices' => $choices,
            'key' => $sheetVersion === null ? null : $this->makeParticipantKey('npc', $id, $sheetVersion),
        ];
    }

    /**
     * Разрешает выбранный предмет по inventory id.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param int $inventoryId Строка инвентаря.
     * @param string $itemRuleCode Ожидаемый код.
     *
     * @return ItemSpec Живой предмет.
     *
     * @throws GameInvalidException Если instance невалиден.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    private function selectedItem(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $inventoryId,
        string $itemRuleCode,
    ): ItemSpec {
        $this->assertInventoryItem($choices, $inventoryId, $itemRuleCode, $spaceId, $rulesRevision);

        $inventory = $choices['inventory'];
        foreach ($inventory as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                return $this->effectiveItem($spaceId, $rulesRevision, $row);
            }
        }

        throw new GameInvalidException('Game strike inventory item is invalid');
    }

    /**
     * Разрешает выбранный предмет без доверия к его rule code.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param int $inventoryId Строка инвентаря.
     *
     * @return ItemSpec Живой предмет.
     *
     * @throws GameInvalidException Если instance невалиден.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    private function selectedItemById(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $inventoryId,
    ): ItemSpec {
        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game strike inventory is invalid');
        }

        $rows = array_values(array_filter(
            $inventory,
            static fn (mixed $row): bool => is_array($row) && ($row['id'] ?? null) === $inventoryId,
        ));
        $code = $rows[0]['ruleCode'] ?? null;
        if (count($rows) !== 1 || !is_string($code) || ($rows[0]['equipped'] ?? false) !== true) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        return $this->effectiveItem($spaceId, $rulesRevision, $rows[0]);
    }

    /**
     * Применяет live operations выбранной inventory instance к live item.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $row Строка инвентаря.
     *
     * @return ItemSpec Effective spec.
     *
     * @throws GameInvalidException Если item или modifier битые.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    private function effectiveItem(int $spaceId, int $rulesRevision, array $row): ItemSpec
    {
        $code = $row['ruleCode'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new GameInvalidException('Game strike inventory item is invalid');
        }

        $slice = $this->slice($spaceId, $rulesRevision);
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof ItemSpec) {
            throw new GameInvalidException('Game strike item is invalid');
        }

        return ItemModifierOperations::apply(
            $spec,
            $this->modifierSpecs($slice, $row['modifiers'] ?? null),
            $rule->getKeywordCodes(),
        );
    }

    /**
     * Разрешает live modifier specs строки инвентаря.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param mixed $codes Коды модификаторов.
     *
     * @return array<int, ItemModifierSpec> Specs.
     *
     * @throws GameInvalidException Если коды или spec битые.
     */
    private function modifierSpecs(CharacterRuleSlice $slice, mixed $codes): array
    {
        if ($codes === null || $codes === []) {
            return [];
        }

        if (!is_array($codes)) {
            throw new GameInvalidException('Game strike item modifiers are invalid');
        }

        $specs = [];
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                throw new GameInvalidException('Game strike item modifier is invalid');
            }

            $rule = $slice->findLive($code);
            $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof ItemModifierSpec) {
                throw new GameInvalidException('Game strike item modifier is invalid');
            }

            $specs[] = $spec;
        }

        return $specs;
    }

    /**
     * Выбирает лучшее мастерство носимого оружия этого профиля.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string $profileType Тип профиля.
     * @param string|null $participantKey Ключ листа защитника.
     *
     * @return DimensionalNumber|null Лучшее мастерство или null.
     *
     * @throws GameInvalidException Если characteristic или domain неоднозначны.
     */
    private function bestEquippedMastery(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        string $profileType,
        ?string $participantKey = null,
    ): ?DimensionalNumber {
        $best = null;
        $inventory = $choices['inventory'] ?? [];
        if (!is_array($inventory)) {
            $inventory = [];
        }

        foreach ($inventory as $row) {
            if (!is_array($row) || !is_string($row['ruleCode'] ?? null)) {
                continue;
            }

            $rule = $slice->findLive($row['ruleCode']);
            $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof ItemSpec || !$this->isCarried($row, $spec)) {
                continue;
            }

            $weapon = $spec->getWeapon();
            if (!$weapon instanceof WeaponBlock) {
                continue;
            }

            $matches = false;
            foreach ($weapon->getWeaponProfiles() as $profile) {
                if ($profile->getType() === $profileType) {
                    $matches = true;
                }
            }

            if (!$matches || $spec->getProficiencyFamilyCode() === null) {
                continue;
            }

            $mastery = $this->mastery(
                $slice,
                $sheet,
                $choices,
                $spec->getProficiencyFamilyCode(),
                $profileType,
                $participantKey,
            );
            if ($best === null || $mastery->compare($best) > 0) {
                $best = $mastery;
            }
        }

        if ($best instanceof DimensionalNumber) {
            return $best;
        }

        return $this->formulaContext($slice, $sheet, $choices, $participantKey)
            ->findCharacteristic($this->combatCharacteristic($slice, $profileType));
    }

    private function mastery(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        ?string $familyCode,
        string $profileType,
        ?string $participantKey = null,
    ): DimensionalNumber {
        if (is_array($sheet['sheet'] ?? null) && is_array($sheet['choices'] ?? null)) {
            $sheet = $sheet['sheet'];
        }

        if ($familyCode === null || $familyCode === '') {
            throw new GameInvalidException('Game strike weapon family is invalid');
        }

        $characteristicCode = null;
        $masterySpec = null;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (
                $spec instanceof CharacteristicSpec
                && in_array($profileType, $spec->getWeaponMasteryProfiles(), true)
            ) {
                if ($characteristicCode !== null) {
                    throw new GameInvalidException('Game strike characteristic is ambiguous');
                }

                $characteristicCode = $rule->getCode();
                $masterySpec = $spec;
            }
        }

        $derived = $this->formulaContext($slice, $sheet, $choices, $participantKey)->findCharacteristic((string) $characteristicCode);
        $purchases = $sheet['characteristicPurchases'] ?? null;
        $base = $derived instanceof DimensionalNumber
            ? new CharacteristicNumber($derived->getBase(), $derived->getSize())
            : null;
        if ($base === null && is_array($purchases)) {
            foreach ($purchases as $row) {
                if (is_array($row) && ($row['characteristicCode'] ?? null) === $characteristicCode) {
                    $value = $row['value'] ?? null;
                    if (!is_array($value) || !is_int($value['base'] ?? null) || !is_int($value['size'] ?? null)) {
                        throw new GameInvalidException('Game strike characteristic is invalid');
                    }

                    if ($base !== null) {
                        throw new GameInvalidException('Game strike characteristic is ambiguous');
                    }

                    $base = new CharacteristicNumber($value['base'], $value['size']);
                }
            }
        }

        if ($base === null && $masterySpec !== null) {
            $automatic = $masterySpec->getAutomatic();
            if ($automatic instanceof CharacteristicNumber) {
                $base = $automatic;
            }
        }

        if ($characteristicCode === null || $base === null) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        $computed = $this->computedSheet($slice, $sheet, $choices);
        $stored = $computed['weaponFamilyMastery'][$familyCode][$profileType] ?? null;
        if (is_array($stored) && is_int($stored['base'] ?? null) && is_int($stored['size'] ?? null)) {
            return new CharacteristicNumber($stored['base'], $stored['size']);
        }

        return $base;
    }

    /**
     * Посчитанный лист с картой семейств.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<string, mixed> Sheet.
     */
    private function computedSheet(CharacterRuleSlice $slice, array $sheet, array $choices): array
    {
        if (is_array($sheet['sheet'] ?? null) && is_array($sheet['choices'] ?? null)) {
            $choices = is_array($sheet['choices']) ? $sheet['choices'] : $choices;
            $sheet = $sheet['sheet'];
        }

        return $this->sheets->compute($slice, $choices, $sheet);
    }

    /**
     * Проверяет выбранную надетую строку инвентаря.
     *
     * @param array<string, mixed> $choices Выборы листа.
     * @param int $inventoryId Идентификатор строки.
     * @param string $itemRuleCode Ожидаемый код предмета.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка отсутствует или не совпадает.
     */
    private function assertInventoryItem(
        array $choices,
        int $inventoryId,
        string $itemRuleCode,
        int $spaceId,
        int $rulesRevision,
    ): void {
        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game strike inventory is invalid');
        }

        $matches = [];
        foreach ($inventory as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                $matches[] = $row;
            }
        }

        $item = $this->item($spaceId, $rulesRevision, $itemRuleCode);
        if (
            count($matches) !== 1
            || !$this->isCarried($matches[0], $item)
            || ($matches[0]['ruleCode'] ?? null) !== $itemRuleCode
        ) {
            throw new GameInvalidException('Game strike inventory item is invalid');
        }
    }

    /**
     * Строка в руках: надета или врождённое оружие.
     *
     * @param array<string, mixed> $row Строка инвентаря.
     * @param ItemSpec $item Предмет.
     *
     * @return bool true, если доступна в бою.
     */
    private function isCarried(array $row, ItemSpec $item): bool
    {
        return ($row['equipped'] ?? false) === true || $item->isInnate();
    }

    /**
     * Сдвиг точности текущего удара.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType strike, throw или shoot.
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
     * Возвращает единственную строку боеприпаса с положительным остатком.
     *
     * @param array<string, mixed> $choices Выборы.
     * @param int $inventoryId Идентификатор строки.
     *
     * @return array{ruleCode: string} Строка.
     *
     * @throws GameInvalidException Если строка отсутствует или неоднозначна.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    private function selectedAmmunition(array $choices, int $inventoryId): array
    {
        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game ammunition inventory is invalid');
        }

        $matches = [];
        foreach ($inventory as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                $matches[] = $row;
            }
        }

        if (count($matches) !== 1) {
            throw new GameInvalidException('Game ammunition inventory item is missing or ambiguous');
        }

        $ruleCode = $matches[0]['ruleCode'] ?? null;
        $quantity = $matches[0]['quantity'] ?? null;
        if (
            !is_string($ruleCode)
            || $ruleCode === ''
            || !is_int($quantity)
            || $quantity < 1
        ) {
            throw new GameInvalidException('Game ammunition inventory item is invalid');
        }

        return ['ruleCode' => $ruleCode];
    }

    /**
     * Проверяет положительный остаток выбранной строки.
     *
     * @param array<string, mixed> $choices Выборы.
     * @param int $inventoryId Идентификатор строки.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка отсутствует, неоднозначна или пуста.
     */
    private function assertPositiveQuantity(array $choices, int $inventoryId): void
    {
        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new GameInvalidException('Game strike inventory is invalid');
        }

        $matches = [];
        foreach ($inventory as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                $matches[] = $row;
            }
        }

        $quantity = $matches[0]['quantity'] ?? null;
        if (count($matches) !== 1 || !is_int($quantity) || $quantity < 1) {
            throw new GameInvalidException('Game strike inventory item is unavailable');
        }
    }

    /**
     * Лист персонажа цели.
     *
     * @param int $characterId Персонаж.
     *
     * @return array<string, mixed> sheet.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    private function storedSheet(int $characterId): array
    {
        try {
            return $this->characters->get($characterId)->getSheet();
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }
    }

    /**
     * Лист и выборы цели.
     *
     * @param string $defenderKind Character или npc.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Снимок sheet.
     * @param array<string, mixed>|null $npcChoices Документ choices.
     *
     * @param int|null $sheetVersion Версия листа NPC.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameInvalidException Если снимка NPC нет.
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function defenderDocuments(
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
        ?int $sheetVersion = null,
    ): array {
        if ($defenderKind === 'npc') {
            return [
                'sheet' => $this->npcSheet($npcSheet),
                'choices' => $npcChoices ?? throw new GameInvalidException('Game strike npc choices are missing'),
                'key' => $sheetVersion === null ? null : $this->makeParticipantKey('npc', $defenderId, $sheetVersion),
            ];
        }

        try {
            $record = $this->characters->get($defenderId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }

        return [
            'sheet' => $this->liveSheet($record),
            'choices' => $record->getChoices(),
            'key' => $this->makeParticipantKey('character', $defenderId, $record->getActualVersion()),
        ];
    }

    /**
     * Снимок sheet NPC.
     *
     * @param array<string, mixed>|null $npcSheet Снимок или null.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если снимка нет.
     */
    private function npcSheet(?array $npcSheet): array
    {
        if ($npcSheet === null) {
            throw new GameInvalidException('Game strike npc sheet is missing');
        }

        return $npcSheet;
    }

    /**
     * Контекст формулы: закупки листа, база расы, автоматические характеристики.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string|null $participantKey Ключ участника и версии листа.
     *
     * @return FormulaContext Контекст.
     *
     * @throws CharacterInvalidException Если снимок битый.
     */
    private function formulaContext(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        ?string $participantKey = null,
    ): FormulaContext {
        if ($participantKey !== null && isset($this->contextCache[$participantKey])) {
            return $this->contextCache[$participantKey];
        }

        if (is_array($sheet['sheet'] ?? null) && is_array($sheet['choices'] ?? null)) {
            $choices = is_array($sheet['choices']) ? $sheet['choices'] : $choices;
            $sheet = $sheet['sheet'];
        }

        $computed = $this->sheets->compute($slice, $choices, $sheet);
        $context = $this->formulaContexts->build($computed);
        if ($participantKey !== null) {
            $this->sheetCache[$participantKey] = $computed;
            $this->contextCache[$participantKey] = $context;
        }

        return $context;
    }

    /**
     * Посчитанный sheet actual.
     *
     * @param CharacterRecord $record Строка.
     *
     * @return array<string, mixed> Sheet.
     */
    private function liveSheet(CharacterRecord $record): array
    {
        $key = $this->makeParticipantKey('character', $record->getId(), $record->getActualVersion());
        if (isset($this->sheetCache[$key])) {
            return $this->sheetCache[$key];
        }

        $sheet = $this->sheets->compute(
            $this->slice($record->getSpaceId(), $record->getRulesRevision()),
            $record->getChoices(),
            $record->getSheet(),
        );
        $this->sheetCache[$key] = $sheet;
        $this->contextCache[$key] = $this->formulaContexts->build($sheet);

        return $sheet;
    }

    /**
     * Живое правило.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код.
     *
     * @return void
     *
     * @throws GameInvalidException Если кода нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function assertLive(int $spaceId, int $rulesRevision, string $ruleCode): void
    {
        $live = $this->slice($spaceId, $rulesRevision)->findLive($ruleCode);
        if ($live === null) {
            throw new GameInvalidException('Game strike rule is not live');
        }
    }

    /**
     * Предмет живого правила.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код.
     *
     * @return ItemSpec Spec.
     *
     * @throws GameInvalidException Если это не предмет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function item(int $spaceId, int $rulesRevision, string $ruleCode): ItemSpec
    {
        $rule = $this->slice($spaceId, $rulesRevision)->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof ItemSpec) {
            throw new GameInvalidException('Game strike item is invalid');
        }

        return $spec;
    }

    /**
     * Срез ревизии игры. Код Character наружу не выходит.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return CharacterRuleSlice Срез.
     *
     * @throws GameInvalidException Если срез битый.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function slice(int $spaceId, int $rulesRevision): CharacterRuleSlice
    {
        $key = $spaceId . ':' . $rulesRevision;
        if (isset($this->ruleSliceCache[$key])) {
            return $this->ruleSliceCache[$key];
        }

        try {
            $slice = $this->ruleSlices->get($spaceId, $rulesRevision);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike revision was not found', $exception);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike revision is invalid', $exception);
        }

        $this->ruleSliceCache[$key] = $slice;

        return $slice;
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
        $base = $this->actionBase($slice, $actionRuleCode);
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
        $ruleCode = $this->reactionRuleCode($slice->getSpaceId(), $slice->getRevision(), $reaction);
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

            if (!$this->soakTailApplies($effect, $profileType, $hitNumber)) {
                continue;
            }

            return $effect;
        }

        return null;
    }

    /**
     * Область хвоста смягчения накрывает попадание.
     *
     * @param array<string, mixed> $effect Эффект.
     * @param string $profileType Тип профиля.
     * @param int $hitNumber Номер попадания.
     *
     * @return bool true, если накрывает.
     */
    private function soakTailApplies(array $effect, string $profileType, int $hitNumber): bool
    {
        $scope = is_array($effect['scope'] ?? null) ? $effect['scope'] : [];
        $components = is_array($scope['components'] ?? null) ? $scope['components'] : [];
        if (!in_array($profileType, $components, true)) {
            return false;
        }

        $hitCount = $scope['hit_count'] ?? 1;

        return $hitCount === 'all' || (is_int($hitCount) && $hitNumber <= $hitCount);
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
        $slice = $this->slice($spaceId, $rulesRevision);
        $documents = $this->attackerDocuments($actorKind, $actorId, $npcSheet, $npcChoices, $npcVersion);
        $value = $this->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key'])
            ->findCharacteristic(self::REACTION_CHARACTERISTIC);
        if ($value === null) {
            throw new GameInvalidException('Game strike reaction characteristic is invalid');
        }

        return max(0, $value->toInteger());
    }

}
