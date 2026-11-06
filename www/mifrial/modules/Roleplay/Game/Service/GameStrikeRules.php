<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Game\Dto\GameStrikeActorRef;
use Mifrial\Roleplay\Game\Dto\GameStrikeAttack;
use Mifrial\Roleplay\Game\Dto\GameStrikeBlockRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeDodgeRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeHitRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeProfileRef;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackCharacteristicModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionCheckModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionDurabilityShaveEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Проверка выбора удара по срезу ревизии игры.
 */
final class GameStrikeRules
{
    private readonly GameStrikeSoaks $soaks;

    private readonly GameStrikeSplits $splits;

    private readonly GameActionResourceResolver $resourceResolver;

    private readonly GameStrikeSuccessModifier $successModifier;

    private readonly GameStrikeMovement $movement;

    private readonly GameStrikeAttackFactory $attacks;

    private readonly GameStrikeActionCards $cards;

    private readonly GameStrikeDefense $defense;

    private readonly GameStrikeChecks $checks;

    private readonly GameStrikeMastery $mastery;

    private readonly GameStrikeHitCheck $hitCheck;

    private readonly GameStrikeRolls $rolls;

    private readonly GameStrikeDistance $distance;

    private readonly GameStrikeDamage $damage;

    /**
     * Создаёт проверку.
     *
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     * @param GameCheckRoll $hitRoll Бросок попадания.
     * @param GameStrikeLayers $layers Сопротивление слоёв.
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeEquipment $equipment Снаряжение удара.
     * @param GameStrikeInventorySpend $inventorySpend Списание предмета.
     * @param GameStrikeParticipants $participants Участники удара.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $formulaEvaluations,
        private readonly GameCheckRoll $hitRoll,
        private readonly GameStrikeLayers $layers,
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeEquipment $equipment,
        private readonly GameStrikeInventorySpend $inventorySpend,
        private readonly GameStrikeParticipants $participants,
    ) {
        $this->soaks = new GameStrikeSoaks();
        $this->splits = new GameStrikeSplits();
        $this->resourceResolver = new GameActionResourceResolver();
        $this->successModifier = new GameStrikeSuccessModifier();
        $this->movement = new GameStrikeMovement();
        $this->attacks = new GameStrikeAttackFactory(
            $slices,
            $participants,
            $equipment,
            new GameStrikeProfileContext($formulaEvaluations),
        );
        $this->distance = new GameStrikeDistance($formulaEvaluations, $this->movement);
        $this->damage = new GameStrikeDamage($formulaEvaluations);
        $this->cards = new GameStrikeActionCards($slices, $participants);
        $this->checks = new GameStrikeChecks($slices, $participants, $equipment, $this->cards);
        $this->mastery = new GameStrikeMastery($participants, $equipment);
        $this->hitCheck = new GameStrikeHitCheck($hitRoll, $slices);
        $this->defense = new GameStrikeDefense($slices, $participants, $layers, $this->soaks, $this->cards);
        $this->rolls = new GameStrikeRolls(
            $hitRoll,
            $slices,
            $participants,
            $equipment,
            $this->mastery,
            $this->checks,
        );
    }

    /**
     * Снимок удара по запросу.
     *
     * @param GameStrikeTarget $target Запрос удара.
     *
     * @return GameStrikeAttack Снимок.
     *
     * @throws GameInvalidException Если вид, профиль или лист битые.
     * @throws RuleInvalidException Если база профиля битая.
     * @throws CharacterInvalidException Если снимок битый.
     * @throws CharacterNotFoundException Если персонажа нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function attack(GameStrikeTarget $target): GameStrikeAttack
    {
        return $this->attacks->build($target);
    }

    /**
     * Расчёт защиты.
     *
     * @return GameStrikeDefense Защита.
     */
    public function defense(): GameStrikeDefense
    {
        return $this->defense;
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
     * @param int $instanceIndex Экземпляр внутри стопки.
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
        int $instanceIndex = 0,
    ): void {
        $this->checks->assertAttack(
            $spaceId,
            $rulesRevision,
            $actionRuleCode,
            $itemInventoryId,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $attackerKind,
            $attackerId,
            $npcChoices,
            $instanceIndex,
        );
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
        return GameStrikeRuleLookup::actionBase($this->slice($spaceId, $rulesRevision), $actionRuleCode)?->getPush();
    }

    /**
     * Модификаторы проверки с пассивных карточек.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, int> $levels Уровни способностей актора.
     * @param string $checkCode Код проверки.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function passiveCheckAdvantages(CharacterRuleSlice $slice, array $levels, string $checkCode): array
    {
        $rows = [];
        foreach ($slice->getLiveRules() as $rule) {
            $level = $levels[$rule->getCode()] ?? 0;
            if ($level < 1) {
                continue;
            }

            $spec = $rule->getSpec();
            if (!$spec instanceof AbilityPlainSpec) {
                continue;
            }

            foreach ($spec->getBase()->getActionEffects() as $effect) {
                if (
                    !$effect instanceof CurrentActionCheckModifierEffect
                    || !in_array($checkCode, $effect->getCheckCodes(), true)
                ) {
                    continue;
                }

                $source = $effect->getSourceCode();
                $source = is_string($source) && $source !== '' ? $source : null;
                $rows[] = new RollAdvantage(
                    $source,
                    $effect->getDelta(),
                    AdvantageSourceLabels::getLabel($source),
                );
            }
        }

        return $rows;
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
        return GameStrikeRuleLookup::actionBase($this->slice($spaceId, $rulesRevision), $actionRuleCode);
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
        return $this->cards->liftsMaxWeapons($spaceId, $rulesRevision, $actionRuleCode, $actorKind, $actorId, $npcChoices);
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
        return $this->cards->isSequentialStrike($spaceId, $rulesRevision, $actionRuleCode);
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
        return $this->cards->isWideAttack($spaceId, $rulesRevision, $actionRuleCode);
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
        $this->checks->assertAttackTargets($spaceId, $rulesRevision, $actionRuleCode, $targetCount);
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
        $base = GameStrikeRuleLookup::actionBase($this->slice($spaceId, $rulesRevision), $actionRuleCode);
        $delta = $base?->getWideTargetCheckAdvantage() ?? 0;
        if ($delta === 0 || $targetCount < 2) {
            return null;
        }

        return new RollAdvantage(
            'circumstances',
            $delta * ($targetCount - 1),
            AdvantageSourceLabels::getLabel('circumstances'),
        );
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
        return $this->inventorySpend->resolve(
            $spaceId,
            $rulesRevision,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $ammunitionInventoryId,
            $attackerKind,
            $attackerId,
            $npcChoices,
        );
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
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int $gripBonus Бонус удержания к Силе удара.
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
        int $strengthDrop = 0,
        int $gripBonus = 0,
    ): DimensionalNumber {
        try {
            return $this->damage->amount($this->attacks->build($this->target(
                $spaceId,
                $rulesRevision,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $itemInventoryId,
                $attackerKind,
                $attackerId,
                $npcSheet,
                $npcChoices,
                $sheetVersion,
                $actionCharacteristicModifier,
                $strengthDrop,
                $gripBonus,
            )));
        } catch (GameInvalidException $exception) {
            throw $exception;
        } catch (RuleInvalidException | CharacterInvalidException | CharacterNotFoundException $exception) {
            throw new GameInvalidException('Game strike damage is invalid', $exception);
        }
    }

    /**
     * Сила удара выбранного профиля: база профиля или характеристика, затем сдвиг карточки.
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
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int $gripBonus Бонус удержания к Силе удара.
     *
     * @return DimensionalNumber Пара характеристики.
     *
     * @throws GameInvalidException Если профиль, лист или сила битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function strikeStrength(
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
        int $strengthDrop = 0,
        int $gripBonus = 0,
    ): DimensionalNumber {
        try {
            return $this->damage->strength($this->attacks->build($this->target(
                $spaceId,
                $rulesRevision,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $itemInventoryId,
                $attackerKind,
                $attackerId,
                $npcSheet,
                $npcChoices,
                $sheetVersion,
                $actionCharacteristicModifier,
                $strengthDrop,
                $gripBonus,
            )));
        } catch (GameInvalidException $exception) {
            throw $exception;
        } catch (RuleInvalidException | CharacterInvalidException | CharacterNotFoundException $exception) {
            throw new GameInvalidException('Game strike strength is invalid', $exception);
        }
    }

    /**
     * Запрос удара из идентификаторов.
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
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int $gripBonus Бонус удержания к Силе удара.
     *
     * @return GameStrikeTarget Запрос удара.
     */
    private function target(
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
        ?int $sheetVersion,
        ?int $actionCharacteristicModifier,
        int $strengthDrop,
        int $gripBonus,
    ): GameStrikeTarget {
        return new GameStrikeTarget(
            new GameStrikeProfileRef(
                $spaceId,
                $rulesRevision,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $itemInventoryId,
            ),
            new GameStrikeActorRef($attackerKind, $attackerId, $npcSheet, $npcChoices, $sheetVersion),
            $actionCharacteristicModifier,
            $strengthDrop,
            $gripBonus,
        );
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
     * @param int|null $actionCharacteristicModifier Сдвиг характеристики действия.
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int $gripBonus Бонус удержания к Силе.
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
        ?int $actionCharacteristicModifier = null,
        int $strengthDrop = 0,
        int $gripBonus = 0,
    ): DimensionalNumber {
        try {
            return $this->damage->penetration($this->attacks->build($this->target(
                $spaceId,
                $rulesRevision,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $itemInventoryId,
                $attackerKind,
                $attackerId,
                $npcSheet,
                $npcChoices,
                $sheetVersion,
                $actionCharacteristicModifier,
                $strengthDrop,
                $gripBonus,
            )));
        } catch (RuleInvalidException | CharacterInvalidException | CharacterNotFoundException $exception) {
            throw new GameInvalidException('Game strike penetration is invalid', $exception);
        }
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
     */
    public function findHitCode(int $spaceId, int $rulesRevision): string
    {
        return $this->hitCheck->findHitCode($spaceId, $rulesRevision);
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
        return $this->hitCheck->isHitAutoFail($roll);
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
        return $this->rolls->hit(new GameStrikeHitRequest(
            $this->target(
                $spaceId,
                $rulesRevision,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $itemInventoryId,
                $attackerKind,
                $attackerId,
                $npcSheet,
                $npcChoices,
                $sheetVersion,
                null,
                0,
                0,
            ),
            $ruleCode,
            $actionRuleCode,
            $advantages,
            $pendingAccuracyDelta,
            $pendingAdjustment,
        ));
    }

    public function isUnconscious(int $spaceId, int $rulesRevision, array $sheet): bool
    {
        return $this->hitCheck->isUnconscious($spaceId, $rulesRevision, $sheet);
    }

    /**
     * Бросок уклона: мастерство боя против сырого пула атаки.
     *
     * Уклонение всегда использует мастерство боя, а не мастерство оружия,
     * которым совершён выстрел или бросок.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код hit-check.
     * @param array<string, mixed> $sheet Лист защитника.
     * @param array<string, mixed> $choices Выборы защитника.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string|null $participantKey Ключ листа защитника.
     * @param array<int, RollAdvantage> $advantages Преимущества проверки.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     * @param string|null $profileType Тип профиля атаки: strike, throw или shoot.
     *
     * @return array<string, mixed> Итог.
     */
    public function rollDodge(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        array $sheet,
        array $choices,
        array $attackerRoll,
        ?string $participantKey = null,
        array $advantages = [],
        ?DimensionalNumber $shotDistance = null,
        ?string $profileType = null,
    ): array {
        return $this->rolls->dodge(new GameStrikeDodgeRequest(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            $sheet,
            $choices,
            $attackerRoll,
            $participantKey,
            $advantages,
            $shotDistance,
            $profileType,
        ));
    }

    /**
     * Переоценивает атаку против сырого пула уклона или готовой трудности.
     *
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $defenderRoll Бросок уклона или готовый {base,size}.
     *
     * @return array<string, mixed> Бросок атаки.
     */
    public function rerateHit(array $attackerRoll, array $defenderRoll): array
    {
        return $this->hitCheck->rerate($attackerRoll, $defenderRoll);
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
        return $this->rolls->block(new GameStrikeBlockRequest(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            $sheet,
            $choices,
            $blockItemInventoryId,
            $blockItemProfileIndex,
            $attackerRoll,
            $participantKey,
            $advantages,
        ));
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
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
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
            $source = is_string($source) && $source !== '' ? $source : null;
            $rows[] = new RollAdvantage(
                $source,
                $effect->getDelta(),
                AdvantageSourceLabels::getLabel($source),
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
        return $this->cards->reactionRuleCode($spaceId, $rulesRevision, $reaction);
    }

    /**
     * Первая по коду живая карточка-потомок, закрывающая процесс.
     *
     * Потомок не обязан быть type ability: узел живёт в общей базе, поэтому
     * подходит и skill. Тип карточки не фильтруется.
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
        return $this->cards->closingAbility($spaceId, $rulesRevision, $processCode, $counters, $abilityLevels);
    }

    /**
     * Текущий сдвиг характеристики атаки карточки действия.
     *
     * Учитывает эффекты самой карточки и её дочерних навыков
     * (`parent_ability_code`) с уровнем ≥ 1.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param array{damageTypeCode?: string|null, occupyHands?: int} $attackContext
     *        Контекст профиля: тип урона и занятые оружием руки.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return int Сдвиг.
     */
    public function actionCharacteristicModifier(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        array $attackContext = [],
        array $abilityLevels = [],
    ): int {
        return $this->netSourceDeltas(
            $this->actionCharacteristicDeltas(
                $slice,
                $actionRuleCode,
                $profileType,
                $hitNumber,
                $attackContext,
                $abilityLevels,
            ),
        );
    }

    /**
     * Сдвиг характеристики атаки с бонусом от рейтинга успеха.
     *
     * Складывает по правилу source: от одного источника остаются сильнейший
     * бонус и сильнейший штраф, разные источники суммируются.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param array{damageTypeCode?: string|null, occupyHands?: int} $attackContext
     *        Контекст профиля: тип урона и занятые оружием руки.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     * @param int|null $success Рейтинг успеха или null.
     *
     * @return int Сдвиг.
     */
    public function attackCharacteristicModifier(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        array $attackContext = [],
        array $abilityLevels = [],
        ?int $success = null,
    ): int {
        $entries = $this->actionCharacteristicDeltas(
            $slice,
            $actionRuleCode,
            $profileType,
            $hitNumber,
            $attackContext,
            $abilityLevels,
        );
        if ($success !== null) {
            $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
            if ($base !== null) {
                array_push(
                    $entries,
                    ...$this->successModifier->entriesFrom(
                        $base->getActionEffects(),
                        $profileType,
                        $hitNumber,
                        $success,
                    ),
                );
            }
        }

        return $this->netSourceDeltas($entries);
    }

    /**
     * Вклады «силы удара» карточки и её детей с источниками.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param array{damageTypeCode?: string|null, occupyHands?: int} $attackContext
     *        Контекст профиля: тип урона и занятые оружием руки.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return list<array{source: string|null, delta: int}> Вклады.
     */
    private function actionCharacteristicDeltas(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        array $attackContext = [],
        array $abilityLevels = [],
    ): array {
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        $damageType = $attackContext['damageTypeCode'] ?? null;
        $damageType = $damageType !== '' ? $damageType : null;
        $occupyHands = $attackContext['occupyHands'] ?? 0;

        $entries = [];
        foreach ($this->actionEffectBases($slice, $actionRuleCode, $base, $abilityLevels) as $current) {
            foreach ($current->getActionEffects() as $effect) {
                if (!$effect instanceof CurrentActionAttackCharacteristicModifierEffect) {
                    continue;
                }

                if (!$effect->getScope()->appliesTo($profileType, $hitNumber)) {
                    continue;
                }

                $types = $effect->getDamageTypeCodes();
                if ($types !== [] && ($damageType === null || !in_array($damageType, $types, true))) {
                    continue;
                }

                $minHands = $effect->getMinOccupyHands();
                if ($minHands !== null && $occupyHands < $minHands) {
                    continue;
                }

                $entries[] = ['source' => $effect->getSourceCode(), 'delta' => $effect->getDelta()];
            }
        }

        return $entries;
    }

    /**
     * Нетто вкладов: один источник — сильнейший плюс и сильнейший минус.
     *
     * @param list<array{source: string|null, delta: int}> $entries Вклады.
     *
     * @return int Сумма.
     */
    private function netSourceDeltas(array $entries): int
    {
        $groups = [];
        foreach ($entries as $index => $entry) {
            if ($entry['delta'] === 0) {
                continue;
            }

            $key = $entry['source'] ?? "\0" . $index;
            $groups[$key][] = $entry['delta'];
        }

        $net = 0;
        foreach ($groups as $deltas) {
            $bonus = 0;
            $penalty = 0;
            foreach ($deltas as $delta) {
                if ($delta > 0) {
                    $bonus = max($bonus, $delta);
                } else {
                    $penalty = min($penalty, $delta);
                }
            }

            $net += $bonus + $penalty;
        }

        return $net;
    }

    /**
     * Базы карточки действия и купленных дочерних навыков.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param AbilityBase $base База карточки.
     * @param array<string, int> $abilityLevels Уровни способностей.
     *
     * @return array<int, AbilityBase> Базы.
     */
    private function actionEffectBases(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        AbilityBase $base,
        array $abilityLevels,
    ): array {
        $bases = [$base];
        foreach ($slice->getLiveRules() as $rule) {
            $level = $abilityLevels[$rule->getCode()] ?? 0;
            if ($level < 1) {
                continue;
            }

            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof AbilityActionSpec && !$spec instanceof AbilityPlainSpec) {
                continue;
            }

            $child = $spec->getBase();
            if ($child->getParentAbilityCode() !== $actionRuleCode) {
                continue;
            }

            $bases[] = $child;
        }

        return $bases;
    }

    /**
     * Контекст фильтра характеристического модификатора атаки.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $profileIndex Смещение профиля.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param int $itemInventoryId Строка инвентаря.
     *
     * @return array{damageTypeCode: string|null, occupyHands: int} Контекст.
     *
     * @throws GameInvalidException Если профиль, предмет или строка битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function attackFilterContext(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        array $choices,
        int $itemInventoryId,
    ): array {
        return [
            'damageTypeCode' => $this->damageTypeCode(
                $spaceId,
                $rulesRevision,
                $itemRuleCode,
                $profileType,
                $profileIndex,
            ),
            'occupyHands' => $this->occupyHands(
                $spaceId,
                $rulesRevision,
                $choices,
                $itemInventoryId,
                $itemRuleCode,
                $profileType,
                $profileIndex,
            ),
        ];
    }

    /**
     * Сколько рук занимает оружие в этой атаке.
     *
     * Удержание — `occupy_hands` предмета (с учётом выбора строки инвентаря),
     * плюс `extra_hands` профиля действия. По умолчанию действие занимает
     * столько же рук, сколько удержание.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Authoritative choices.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $profileIndex Смещение профиля.
     *
     * @return int Число рук.
     *
     * @throws GameInvalidException Если предмет, строка или профиль битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function occupyHands(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $itemInventoryId,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): int {
        return $this->equipment->occupyHands(
            $spaceId,
            $rulesRevision,
            $choices,
            $itemInventoryId,
            $itemRuleCode,
            $profileType,
            $profileIndex,
        );
    }

    /**
     * Бонус к Силе удара за удержание оружия.
     *
     * 2–3 руки дают +2, 4 и больше — +3; одна рука без бонуса.
     * Совпадает с фронтом (`CharacterHandsService::gripBonus`).
     *
     * @param int $occupyHands Занятые руки действия.
     *
     * @return int Бонус.
     */
    public function gripBonus(int $occupyHands): int
    {
        return $this->equipment->gripBonus($occupyHands);
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
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return 0;
        }

        return $this->netSourceDeltas(
            $this->successModifier->entriesFrom($base->getActionEffects(), $profileType, $hitNumber, $success),
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
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
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
        $this->checks->assertBlock($spaceId, $rulesRevision, $blockItemRuleCode);
    }

    /**
     * Предмет годится для блока против толчка.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $actionRuleCode Действие.
     * @param string $blockItemRuleCode Предмет блока.
     *
     * @return void
     *
     * @throws GameInvalidException Если предмет не подходит.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertPushBlock(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        string $blockItemRuleCode,
    ): void {
        $this->checks->assertPushBlock($spaceId, $rulesRevision, $actionRuleCode, $blockItemRuleCode);
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
        $this->checks->assertBlockSelection(
            $spaceId,
            $rulesRevision,
            $choices,
            $blockItemInventoryId,
            $blockItemProfileIndex,
            $blockItemRuleCode,
        );
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
     * @return array{
     *     actualVersion: int,
     *     rulesRevision: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>
     * } Снимок.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    public function characterSnapshot(int $characterId): array
    {
        return $this->participants->characterSnapshot($characterId);
    }

    /**
     * Ключ кэша листа участника.
     *
     * @param string $kind Character или npc.
     * @param int $id Идентификатор.
     * @param int $version ActualVersion строки.
     *
     * @return string Ключ.
     */
    public function makeParticipantKey(string $kind, int $id, int $version): string
    {
        return $this->participants->makeParticipantKey($kind, $id, $version);
    }

    /**
     * Имя персонажа для токена чата.
     *
     * @param int $characterId Персонаж.
     *
     * @return string Имя.
     */
    public function characterName(int $characterId): string
    {
        return $this->participants->characterName($characterId);
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
        $documents = $this->participants->defenderDocuments($defenderKind, $defenderId, $npcSheet, $npcChoices, $sheetVersion);

        return $this->splits->operation(
            $slice,
            $this->participants->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key']),
            $injury,
        );
    }

    /**
     * Стойкость цели в том же контексте, что и деление урона.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $defenderKind Вид цели.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Лист NPC.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     * @param int|null $sheetVersion Версия листа.
     *
     * @return DimensionalNumber Стойкость.
     *
     * @throws GameInvalidException Если стойкость битая.
     * @throws GameNotFoundException Если листа нет.
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
        return $this->participants->enduranceOf(
            $spaceId,
            $rulesRevision,
            $defenderKind,
            $defenderId,
            $npcSheet,
            $npcChoices,
            $sheetVersion,
        );
    }

    /**
     * Характеристика листа по коду.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код характеристики.
     *
     * @return DimensionalNumber Значение.
     *
     * @throws GameInvalidException Если характеристики нет.
     */
    public function characteristicOf(array $sheet, string $code): DimensionalNumber
    {
        return $this->participants->characteristicOf($sheet, $code);
    }

    /**
     * Мастерство надетого оружия в выбранном профиле.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param int $inventoryId Строка инвентаря.
     * @param string $itemRuleCode Код предмета.
     * @param string $profileType Профиль: strike, throw или shoot.
     *
     * @return DimensionalNumber Мастерство.
     *
     * @throws GameInvalidException Если предмет или профиль не подходят.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function weaponMasteryOf(
        int $spaceId,
        int $rulesRevision,
        array $sheet,
        array $choices,
        int $inventoryId,
        string $itemRuleCode,
        string $profileType,
    ): DimensionalNumber {
        $this->equipment->assertInventoryItem($choices, $inventoryId, $itemRuleCode, $spaceId, $rulesRevision);
        $item = $this->equipment->selectedItem($spaceId, $rulesRevision, $choices, $inventoryId, $itemRuleCode);
        $weapon = $item->getWeapon();
        $family = $item->getProficiencyFamilyCode();
        if (!$weapon instanceof WeaponBlock || $family === null || $family === '') {
            throw new GameInvalidException('Game strike support weapon is invalid');
        }

        $matches = false;
        foreach ($weapon->getWeaponProfiles() as $profile) {
            if ($profile->getType() === $profileType) {
                $matches = true;
            }
        }

        if (!$matches) {
            throw new GameInvalidException('Game strike support weapon profile is invalid');
        }

        return $this->mastery->of(
            $this->slice($spaceId, $rulesRevision),
            $sheet,
            $choices,
            $family,
            $profileType,
        );
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
        return $this->cards->checkByCharacteristic($spaceId, $rulesRevision, $characteristic);
    }

    /**
     * Код типа урона профиля или null.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Индекс профиля.
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

    /**
     * Тип урона профиля толчка, включая профили щита.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param int $profileIndex Индекс профиля.
     *
     * @return string|null Код типа урона.
     *
     * @throws GameInvalidException Если профиля нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function pushDamageTypeCode(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        int $profileIndex,
    ): ?string {
        $item = $this->item($spaceId, $rulesRevision, $itemRuleCode);
        $profiles = $item->getWeapon()?->getWeaponProfiles()
            ?? $item->getShield()?->getWeaponProfiles()
            ?? [];
        $profile = $profiles[$profileIndex] ?? null;
        if (!$profile instanceof WeaponProfile) {
            throw new GameInvalidException('Game push profile is invalid');
        }

        $code = $profile->getDamage()->getDamageTypeCode();

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Потолок рейтинга попадания типа урона или null.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string|null $damageTypeCode Тип урона.
     *
     * @return int|null Потолок или null.
     *
     * @throws GameInvalidException Если тип урона битый.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function successRatingCap(int $spaceId, int $rulesRevision, ?string $damageTypeCode): ?int
    {
        if ($damageTypeCode === null || $damageTypeCode === '') {
            return null;
        }

        $rule = $this->slice($spaceId, $rulesRevision)->findLive($damageTypeCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof DamageTypeSpec) {
            throw new GameInvalidException('Game strike damage type is invalid');
        }

        return $spec->getMaxSuccessRating();
    }

    /**
     * Рейтинг бонусных успехов процесса: успехи размера броска.
     *
     * Один успех размера броска стоит столько же РУ, сколько обычный успех
     * этой проверки: при размере броска на единицу выше сложности — 2 РУ.
     *
     * @param array<string, mixed> $roll Бросок попадания.
     * @param int $successes Число успехов.
     *
     * @return int Прибавка к РУ.
     */
    public function processBonusRating(array $roll, int $successes): int
    {
        if ($successes <= 0) {
            return 0;
        }

        $rollSize = is_array($roll['roll'] ?? null) && is_int($roll['roll']['size'] ?? null)
            ? $roll['roll']['size']
            : 0;
        $difficultySize = is_array($roll['difficulty'] ?? null) && is_int($roll['difficulty']['size'] ?? null)
            ? $roll['difficulty']['size']
            : $rollSize;
        $factor = 2 ** max(0, $rollSize - $difficultySize);

        return $successes * $factor;
    }

    /**
     * Дистанция, полоса и штраф силы для условий удара.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $attackerKind Character или npc.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     * @param int|null $sheetVersion Версия листа NPC.
     * @param array<string, mixed> $conditions Условия цели.
     * @param int $actionCharacteristicModifier Сдвиг характеристики карточки.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return array{distance: DimensionalNumber, min: DimensionalNumber, max: DimensionalNumber|null,
     *               falloff: DimensionalNumber|null, rangeSize: int, drop: int} Метрики.
     *
     * @throws GameInvalidException Если дистанция вне диапазона или не долетает.
     */
    public function distanceMetrics(
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
        ?int $sheetVersion,
        array $conditions,
        int $actionCharacteristicModifier = 0,
        string $actionRuleCode = '',
    ): array {
        try {
            return $this->distance->metrics($this->attacks->build($this->target(
                $spaceId,
                $rulesRevision,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $itemInventoryId,
                $attackerKind,
                $attackerId,
                $npcSheet,
                $npcChoices,
                $sheetVersion,
                $actionCharacteristicModifier,
                0,
                0,
            )), $conditions, $actionRuleCode);
        } catch (GameInvalidException $exception) {
            throw $exception;
        } catch (RuleInvalidException | RuleSpecShapeException | CharacterInvalidException | CharacterNotFoundException $exception) {
            throw new GameInvalidException('Game strike distance is invalid', $exception);
        }
    }

    /**
     * Проверяет условия цели.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $attackerKind Character или npc.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC.
     * @param array<string, mixed>|null $npcChoices Снимок choices NPC.
     * @param int|null $sheetVersion Версия листа NPC.
     * @param array<string, mixed> $conditions Условия.
     * @param int $actionCharacteristicModifier Сдвиг характеристики карточки.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return void
     *
     * @throws GameInvalidException Если условия неверны.
     */
    public function assertConditions(
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
        ?int $sheetVersion,
        array $conditions,
        int $actionCharacteristicModifier = 0,
        string $actionRuleCode = '',
    ): void {
        $this->distance->assertConditions($this->attacks->build($this->target(
            $spaceId,
            $rulesRevision,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $attackerKind,
            $attackerId,
            $npcSheet,
            $npcChoices,
            $sheetVersion,
            $actionCharacteristicModifier,
            0,
            0,
        )), $conditions, $actionRuleCode);
    }

    /**
     * Размерный шаг расы и её способностей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return DimensionalNumber Шаг.
     */
    public function movementStep(CharacterRuleSlice $slice, array $sheet, array $choices): DimensionalNumber
    {
        return $this->movement->step($slice, $sheet, $choices);
    }

    /**
     * Размерный шаг по коду расы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $raceCode Раса.
     *
     * @return DimensionalNumber Шаг.
     */
    public function movementStepOf(CharacterRuleSlice $slice, array $sheet, string $raceCode): DimensionalNumber
    {
        return $this->movement->stepOf($slice, $sheet, $raceCode);
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
    private function profile(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): WeaponProfile {
        return $this->slices->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex);
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
        return $this->slices->item($spaceId, $rulesRevision, $ruleCode);
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
        return $this->slices->slice($spaceId, $rulesRevision);
    }
}
