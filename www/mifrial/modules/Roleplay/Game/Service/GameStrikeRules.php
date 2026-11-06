<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameStrikeBlockRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeDodgeRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeHitRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
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

    private readonly GameStrikeModifiers $modifiers;

    private readonly GameStrikeSpends $spends;

    private readonly GameStrikeOutcome $outcome;

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
        $this->damage = new GameStrikeDamage($formulaEvaluations, $this->attacks);
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
        $this->modifiers = new GameStrikeModifiers($this->successModifier);
        $this->spends = new GameStrikeSpends($slices, $this->resourceResolver, $this->inventorySpend);
        $this->outcome = new GameStrikeOutcome($slices, $participants, $this->splits, $equipment, $this->mastery);
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
     * Списания удара.
     *
     * @return GameStrikeSpends Списания.
     */
    public function spends(): GameStrikeSpends
    {
        return $this->spends;
    }

    /**
     * Расчёт урона и пробития.
     *
     * @return GameStrikeDamage Урон.
     */
    public function damage(): GameStrikeDamage
    {
        return $this->damage;
    }

    /**
     * Расчёт дистанции.
     *
     * @return GameStrikeDistance Дистанция.
     */
    public function distance(): GameStrikeDistance
    {
        return $this->distance;
    }

    /**
     * Исход удара.
     *
     * @return GameStrikeOutcome Исход.
     */
    public function outcome(): GameStrikeOutcome
    {
        return $this->outcome;
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
        return $this->modifiers->passiveCheckAdvantages($slice, $levels, $checkCode);
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
        return $this->cards->wideTargetCheckAdvantage($spaceId, $rulesRevision, $actionRuleCode, $targetCount);
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
            GameStrikeTarget::of(
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
        return $this->checks->actionCheckAdvantages($slice, $actionRuleCode, $checkCode);
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
        return $this->modifiers->actionCharacteristicModifier(
            $slice,
            $actionRuleCode,
            $profileType,
            $hitNumber,
            $attackContext,
            $abilityLevels,
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
        return $this->modifiers->attackCharacteristicModifier(
            $slice,
            $actionRuleCode,
            $profileType,
            $hitNumber,
            $attackContext,
            $abilityLevels,
            $success,
        );
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
        return $this->modifiers->successRatingCharacteristicModifier(
            $slice,
            $actionRuleCode,
            $profileType,
            $hitNumber,
            $success,
        );
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
