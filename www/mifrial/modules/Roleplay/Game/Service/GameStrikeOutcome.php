<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionDurabilityShaveEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Исход удара: тип урона, рейтинг, надёжность, деление и мастерство.
 */
final class GameStrikeOutcome
{
    /**
     * Принимает срезы, участников, деление и мастерство.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники удара.
     * @param GameStrikeSplits $splits Деление повреждения.
     * @param GameStrikeEquipment $equipment Снаряжение удара.
     * @param GameStrikeMastery $mastery Мастерство оружия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeParticipants $participants,
        private readonly GameStrikeSplits $splits,
        private readonly GameStrikeEquipment $equipment,
        private readonly GameStrikeMastery $mastery,
    ) {
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
        $code = $this->slices->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
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
        $item = $this->slices->item($spaceId, $rulesRevision, $itemRuleCode);
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

        $rule = $this->slices->slice($spaceId, $rulesRevision)->findLive($damageTypeCode);
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

        $slice = $this->slices->slice($spaceId, $rulesRevision);
        $damageType = $this->slices->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex)
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

        $slice = $this->slices->slice($spaceId, $rulesRevision);
        $documents = $this->participants->defenderDocuments($defenderKind, $defenderId, $npcSheet, $npcChoices, $sheetVersion);

        return $this->splits->operation(
            $slice,
            $this->participants->formulaContext($slice, $documents['sheet'], $documents['choices'], $documents['key']),
            $injury,
        );
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
        $family = $this->supportFamily($item, $profileType);

        return $this->mastery->of(
            $this->slices->slice($spaceId, $rulesRevision),
            $sheet,
            $choices,
            $family,
            $profileType,
        );
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
            'occupyHands' => $this->equipment->occupyHands(
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
     * Семейство поддержки с нужным профилем.
     *
     * @param ItemSpec $item Предмет.
     * @param string $profileType Тип профиля.
     *
     * @return string Семейство.
     *
     * @throws GameInvalidException Если оружие или профиль не подходят.
     */
    private function supportFamily(ItemSpec $item, string $profileType): string
    {
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

        return $family;
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
}
