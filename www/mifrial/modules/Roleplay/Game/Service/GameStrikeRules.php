<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Проверка выбора удара по срезу ревизии игры.
 */
final class GameStrikeRules
{
    private readonly GameStrikeSoaks $soaks;

    private readonly GameStrikeAmounts $amounts;

    private readonly GameStrikeSplits $splits;

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
    }

    /**
     * Живые коды и профиль оружия.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
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
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): void {
        $this->assertLive($spaceId, $rulesRevision, $actionRuleCode);
        $this->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex);
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
     * @param string|null $blockItemRuleCode Код предмета блока.
     * @param int $rating Рейтинг попадания.
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
        ?string $blockItemRuleCode,
        int $rating,
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

        return $this->layers->total($this->slice($spaceId, $rulesRevision), $damageType, $documents['sheet'], $documents['choices'], $reaction, $blockItemRuleCode, $rating);
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
     * Версия actual персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return int actual_version.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    public function characterVersion(int $characterId): int
    {
        try {
            return $this->characters->get($characterId)->getActualVersion();
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }
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
