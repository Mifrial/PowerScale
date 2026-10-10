<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperations;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Проверка выбора удара по срезу ревизии игры.
 */
final class GameStrikeRules
{
    private readonly GameStrikeSoaks $soaks;

    private readonly GameStrikeAmounts $amounts;

    private readonly GameStrikeSplits $splits;

    private readonly GameActionResourceResolver $resourceResolver;

    /**
     * Создаёт проверку.
     *
     * @param ICharacterRuleSlices $ruleSlices Срез.
     * @param ICharacters $characters Лист.
     * @param ICharacterFormulaContexts $formulaContexts Контекст листа.
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     * @param GameCheckRoll $hitRoll Бросок попадания.
     * @param GameStrikeLayers $layers Сопротивление слоёв.
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
    ) {
        $this->soaks = new GameStrikeSoaks();
        $this->amounts = new GameStrikeAmounts();
        $this->splits = new GameStrikeSplits();
        $this->resourceResolver = new GameActionResourceResolver();
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
    ): array {
        return $this->resourceResolver->resolve(
            $this->slice($spaceId, $rulesRevision),
            $actionRuleCode,
            $itemInventoryId,
            $itemRuleCode,
            $choices,
            $sheet,
            $chosenAmounts,
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

        $this->assertInventoryItem($choices, $itemInventoryId, $itemRuleCode);
    }

    /**
     * Число урона профиля для атакующего. Рейтинг попадания считает rateHit.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param string $attackerKind Character или npc.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
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
        string $attackerKind,
        int $attackerId,
        ?array $npcSheet,
    ): DimensionalNumber {
        $profile = $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex);
        if ($attackerKind !== 'character' && $attackerKind !== 'npc') {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        try {
            $context = $attackerKind === 'npc'
                ? $this->formulaContexts->build($this->npcSheet($npcSheet))
                : $this->formulaContexts->buildStored($attackerId);

            return $this->formulaEvaluations->evaluateDimensional(
                $profile->getDamage()->getFormula(),
                $context,
            );
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
    ): DimensionalNumber {
        $documents = $this->attackerDocuments($attackerKind, $attackerId, $npcSheet, $npcChoices);
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
            $context = $this->formulaContexts->build($documents['sheet']);

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
        );
    }

    /**
     * Смягчение уклонения цели. В лист не пишется.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param string $defenderKind Character или npc.
     * @param int $defenderId Цель.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
     *
     * @return DimensionalNumber Пара после сдвига.
     *
     * @throws GameInvalidException Если карточка, закупка или профиль битые.
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
    ): DimensionalNumber {
        if ($defenderKind !== 'character' && $defenderKind !== 'npc') {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $benefit = $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
            ->getDodgeBenefit() ?? -3;
        $sheet = $defenderKind === 'npc' ? $this->npcSheet($npcSheet) : $this->storedSheet($defenderId);

        return $this->soaks->shift($this->slice($spaceId, $rulesRevision), $this->formulaContexts, $sheet, $benefit);
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
     * Рейтинг попадания атакующего.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код карточки.
     * @param string $attackerKind Character или npc.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcSheet Снимок sheet NPC или null.
     *
     * @return int CheckRating.
     *
     * @throws GameInvalidException Если карточка, пул или лист чужие.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    public function rateHit(
        int $spaceId,
        int $rulesRevision,
        string $ruleCode,
        string $attackerKind,
        int $attackerId,
        ?array $npcSheet,
    ): int {
        if ($attackerKind !== 'character' && $attackerKind !== 'npc') {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $sheet = $attackerKind === 'npc' ? $npcSheet : $this->hitRoll->characterSheet($attackerId)['sheet'];
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game strike npc sheet is missing');
        }

        return $this->hitRoll->throwHit($spaceId, $rulesRevision, $ruleCode, $sheet)['rating'];
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
    ): array {
        $documents = $this->attackerDocuments($attackerKind, $attackerId, $npcSheet, $npcChoices);
        $item = $this->selectedItem($spaceId, $rulesRevision, $documents['choices'], $itemInventoryId, $itemRuleCode);
        $mastery = $this->mastery(
            $this->slice($spaceId, $rulesRevision),
            $documents['sheet'],
            $documents['choices'],
            $item->getProficiencyFamilyCode(),
            $profileType,
        );
        return $this->hitRoll->throwHitWithMastery(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            $documents['sheet'],
            $mastery,
            null,
            ['base' => 0, 'size' => 0],
        );
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
        );

        return $this->hitRoll->throwHitWithMastery(
            $spaceId,
            $rulesRevision,
            $ruleCode,
            $sheet,
            $mastery,
            $profile->getEfficiency(),
            $attackerRoll['roll'],
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
        if (!is_array($row)
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
            if (is_array($row)
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
            'sheet' => $record->getSheet(),
        ];
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
    ): array {
        if ($defenderKind !== 'character' && $defenderKind !== 'npc') {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $sheet = $defenderKind === 'npc' ? $this->npcSheet($npcSheet) : $this->storedSheet($defenderId);

        return $this->splits->operation(
            $this->slice($spaceId, $rulesRevision),
            $this->formulaContexts,
            $sheet,
            $injury,
        );
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
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>} Документы.
     *
     * @throws GameInvalidException Если NPC choices отсутствуют.
     * @throws GameNotFoundException Если персонаж отсутствует.
     */
    private function attackerDocuments(
        string $kind,
        int $id,
        ?array $npcSheet,
        ?array $npcChoices,
    ): array
    {
        if ($kind === 'character') {
            $snapshot = $this->characterSnapshot($id);

            return ['sheet' => $snapshot['sheet'], 'choices' => $snapshot['choices']];
        }

        if ($kind !== 'npc' || !is_array($npcSheet)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $choices = $npcChoices;
        $sheet = $npcSheet;
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game strike npc choices are missing');
        }

        return ['sheet' => $sheet, 'choices' => $choices];
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
        $this->assertInventoryItem($choices, $inventoryId, $itemRuleCode);

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
     * Вычисляет mastery выбранного weapon family.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string|null $familyCode Код family.
     * @param string $profileType Тип профиля.
     *
     * @return DimensionalNumber Derived mastery.
     *
     * @throws GameInvalidException Если characteristic или domain неоднозначны.
     */
    private function mastery(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        ?string $familyCode,
        string $profileType,
    ): DimensionalNumber {
        if (is_array($sheet['sheet'] ?? null) && is_array($sheet['choices'] ?? null)) {
            $sheet = $sheet['sheet'];
        }
        if ($familyCode === null || $familyCode === '') {
            throw new GameInvalidException('Game strike weapon family is invalid');
        }

        $characteristicCode = null;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec
                && in_array($profileType, $spec->getWeaponMasteryProfiles(), true)
            ) {
                if ($characteristicCode !== null) {
                    throw new GameInvalidException('Game strike characteristic is ambiguous');
                }
                $characteristicCode = $rule->getCode();
            }
        }
        if ($characteristicCode === null) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        $purchases = $sheet['characteristicPurchases'] ?? null;
        if (!is_array($purchases)) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        $base = null;
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
        if ($base === null) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        $level = 0;
        $matchedAbility = false;
        $abilities = $choices['abilities'] ?? [];
        if (!is_array($abilities)) {
            throw new GameInvalidException('Game strike ability domain is invalid');
        }
        foreach ($abilities as $ability) {
            if (!is_array($ability)) {
                throw new GameInvalidException('Game strike ability domain is invalid');
            }
            $domainCode = $ability['domainCode'] ?? $ability['domain'] ?? null;
            if ($domainCode === $familyCode) {
                if ($matchedAbility) {
                    throw new GameInvalidException('Game strike ability domain is ambiguous');
                }

                $abilityCode = $ability['ruleCode'] ?? null;
                if (!is_string($abilityCode) || $slice->findLive($abilityCode) === null) {
                    throw new GameInvalidException('Game strike ability domain is invalid');
                }
                if (!is_int($ability['level'] ?? null)) {
                    throw new GameInvalidException('Game strike ability domain is invalid');
                }

                $matchedAbility = true;
                $level = $ability['level'];
            }
        }

        return $level === 0 ? $base : $base->modify($level);
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
    private function assertInventoryItem(array $choices, int $inventoryId, string $itemRuleCode): void
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

        if (count($matches) !== 1
            || ($matches[0]['equipped'] ?? false) !== true
            || ($matches[0]['ruleCode'] ?? null) !== $itemRuleCode
        ) {
            throw new GameInvalidException('Game strike inventory item is invalid');
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
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>} Документы.
     *
     * @throws GameInvalidException Если снимка NPC нет.
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function defenderDocuments(
        string $defenderKind,
        int $defenderId,
        ?array $npcSheet,
        ?array $npcChoices,
    ): array {
        if ($defenderKind === 'npc') {
            return [
                'sheet' => $this->npcSheet($npcSheet),
                'choices' => $npcChoices ?? throw new GameInvalidException('Game strike npc choices are missing'),
            ];
        }

        try {
            $record = $this->characters->get($defenderId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }

        return ['sheet' => $record->getSheet(), 'choices' => $record->getChoices()];
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
        if ($this->slice($spaceId, $rulesRevision)->findLive($ruleCode) === null) {
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
        try {
            return $this->ruleSlices->get($spaceId, $rulesRevision);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike revision was not found', $exception);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike revision is invalid', $exception);
        }
    }
}
