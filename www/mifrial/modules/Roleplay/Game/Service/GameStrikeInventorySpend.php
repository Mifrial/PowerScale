<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;

/**
 * Списание предмета или боеприпаса по live Rule.
 */
final class GameStrikeInventorySpend
{
    /**
     * Принимает срезы, снаряжение и листы персонажей.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeEquipment $equipment Снаряжение.
     * @param ICharacters $characters Листы персонажей.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeEquipment $equipment,
        private readonly ICharacters $characters,
    ) {
    }

    /**
     * Разрешает списание предмета или боеприпаса по live Rule.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
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
    public function resolve(
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
        $choices = $this->choicesOf($attackerKind, $attackerId, $npcChoices);

        return $this->resolveFor(
            $spaceId,
            $rulesRevision,
            $choices,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $ammunitionInventoryId,
        );
    }

    /**
     * Выбирает профиль и списание по уже прочитанным выборам.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Тип профиля.
     * @param int $profileIndex Индекс профиля.
     * @param int $itemInventoryId Строка предмета.
     * @param int|null $ammunitionInventoryId Строка боеприпаса.
     *
     * @return array{kind: string, inventoryId: int, ruleCode: string}|null Списание.
     *
     * @throws GameInvalidException Если профиль или боеприпас невалидны.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function resolveFor(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $itemInventoryId,
        ?int $ammunitionInventoryId,
    ): ?array {
        $profile = $this->spendProfile(
            $spaceId,
            $rulesRevision,
            $choices,
            $itemRuleCode,
            $itemInventoryId,
            $profileType,
            $profileIndex,
        );
        return $this->spendOf(
            $spaceId,
            $rulesRevision,
            $choices,
            $profile,
            $profileType,
            $itemInventoryId,
            $itemRuleCode,
            $ammunitionInventoryId,
        );
    }

    /**
     * Выборы атакующего: персонаж читается по id, NPC приходит снаружи.
     *
     * @param string $kind Вид: character или npc.
     * @param int $id Атакующий.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     *
     * @return array<string, mixed> Выборы.
     *
     * @throws GameInvalidException Если выборов нет.
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function choicesOf(string $kind, int $id, ?array $npcChoices): array
    {
        if ($kind !== 'character') {
            if (!is_array($npcChoices)) {
                throw new GameInvalidException('Game strike attacker choices are invalid');
            }

            return $npcChoices;
        }

        try {
            return $this->characters->get($id)->getChoices();
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike character was not found', $exception);
        }
    }

    /**
     * Выбирает списание по виду профиля.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param WeaponProfile $profile Профиль.
     * @param string $profileType Тип профиля.
     * @param int $itemInventoryId Строка предмета.
     * @param string $itemRuleCode Предмет.
     * @param int|null $ammunitionInventoryId Строка боеприпаса.
     *
     * @return array{kind: string, inventoryId: int, ruleCode: string}|null Списание.
     *
     * @throws GameInvalidException Если профиль или боеприпас невалидны.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function spendOf(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        WeaponProfile $profile,
        string $profileType,
        int $itemInventoryId,
        string $itemRuleCode,
        ?int $ammunitionInventoryId,
    ): ?array {
        return match ($profileType) {
            'strike' => $this->strikeSpend($ammunitionInventoryId),
            'throw' => $this->throwSpend($choices, $profile, $itemInventoryId, $itemRuleCode, $ammunitionInventoryId),
            'shoot' => $this->ammunitionSpend($spaceId, $rulesRevision, $choices, $profile, $ammunitionInventoryId),
            default => throw new GameInvalidException('Game strike ammunition is invalid'),
        };
    }

    /**
     * Профиль выбранного предмета для списания.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param string $itemRuleCode Предмет.
     * @param int $itemInventoryId Строка предмета.
     * @param string $profileType Тип профиля.
     * @param int $profileIndex Индекс профиля.
     *
     * @return WeaponProfile Профиль.
     *
     * @throws GameInvalidException Если предмет или профиль невалидны.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function spendProfile(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        string $itemRuleCode,
        int $itemInventoryId,
        string $profileType,
        int $profileIndex,
    ): WeaponProfile {
        $item = $this->equipment->selectedItem($spaceId, $rulesRevision, $choices, $itemInventoryId, $itemRuleCode);
        $weapon = $item->getWeapon();
        $profiles = $weapon?->getWeaponProfiles() ?? [];
        $profile = $profiles[$profileIndex] ?? null;
        if (!$profile instanceof WeaponProfile || $profile->getType() !== $profileType) {
            throw new GameInvalidException('Game strike profile is invalid');
        }

        return $profile;
    }

    /**
     * Списание у одиночного удара: боеприпас не ожидается.
     *
     * @param int|null $ammunitionInventoryId Строка боеприпаса.
     *
     * @return null Всегда null.
     *
     * @throws GameInvalidException Если боеприпас передан.
     */
    private function strikeSpend(?int $ammunitionInventoryId): null
    {
        if ($ammunitionInventoryId !== null) {
            throw new GameInvalidException('Game strike ammunition is unexpected');
        }

        return null;
    }

    /**
     * Списание брошенного предмета.
     *
     * @param array<string, mixed> $choices Выборы.
     * @param WeaponProfile $profile Профиль.
     * @param int $itemInventoryId Строка предмета.
     * @param string $itemRuleCode Предмет.
     * @param int|null $ammunitionInventoryId Строка боеприпаса.
     *
     * @return array{kind: string, inventoryId: int, ruleCode: string} Списание.
     *
     * @throws GameInvalidException Если режим или остаток невалидны.
     */
    private function throwSpend(
        array $choices,
        WeaponProfile $profile,
        int $itemInventoryId,
        string $itemRuleCode,
        ?int $ammunitionInventoryId,
    ): array {
        $mode = $profile->getAmmunition()?->getMode() ?? 'self';
        if ($mode !== 'self' || $ammunitionInventoryId !== null) {
            throw new GameInvalidException('Game throw ammunition is unexpected');
        }

        $this->assertPositiveQuantity($choices, $itemInventoryId);

        return [
            'kind' => 'decrementInventoryQuantity',
            'inventoryId' => $itemInventoryId,
            'ruleCode' => $itemRuleCode,
        ];
    }

    /**
     * Списание боеприпаса выстрела.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы.
     * @param WeaponProfile $profile Профиль.
     * @param int|null $ammunitionInventoryId Строка боеприпаса.
     *
     * @return array{kind: string, inventoryId: int, ruleCode: string} Списание.
     *
     * @throws GameInvalidException Если боеприпас не подходит.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function ammunitionSpend(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        WeaponProfile $profile,
        ?int $ammunitionInventoryId,
    ): array {
        $spec = $profile->getAmmunition();
        if ($spec?->getMode() !== 'item' || !is_int($ammunitionInventoryId)) {
            throw new GameInvalidException('Game strike ammunition is invalid');
        }

        $keywords = $spec->getKeywordCodes();
        if ($keywords === []) {
            throw new GameInvalidException('Game shoot ammunition requirements are missing');
        }

        $ruleCode = $this->selectedAmmunition($choices, $ammunitionInventoryId)['ruleCode'];
        if (array_diff($keywords, $this->ammunitionKeywords($spaceId, $rulesRevision, $ruleCode)) !== []) {
            throw new GameInvalidException('Game ammunition is incompatible');
        }

        return [
            'kind' => 'decrementInventoryQuantity',
            'inventoryId' => $ammunitionInventoryId,
            'ruleCode' => $ruleCode,
        ];
    }

    /**
     * Ключевые слова live-предмета боеприпаса.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Предмет.
     *
     * @return array<int, string> Ключевые слова.
     *
     * @throws GameInvalidException Если это не предмет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function ammunitionKeywords(int $spaceId, int $rulesRevision, string $ruleCode): array
    {
        $rule = $this->slices->slice($spaceId, $rulesRevision)->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof ItemSpec) {
            throw new GameInvalidException('Game ammunition item is invalid');
        }

        return $rule->getKeywordCodes();
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
     */
    private function selectedAmmunition(array $choices, int $inventoryId): array
    {
        $row = $this->equipment->row(
            $choices,
            $inventoryId,
            'Game ammunition inventory item is missing or ambiguous',
        );
        $ruleCode = $row['ruleCode'] ?? null;
        $quantity = $row['quantity'] ?? null;
        $equipped = $row['equipped'] ?? false;
        if (!is_string($ruleCode) || $ruleCode === '' || $equipped !== true) {
            throw new GameInvalidException('Game ammunition inventory item is invalid');
        }

        if (!is_int($quantity) || $quantity < 1) {
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
        $row = $this->equipment->row($choices, $inventoryId, 'Game strike inventory item is unavailable');
        $quantity = $row['quantity'] ?? null;
        if (!is_int($quantity) || $quantity < 1) {
            throw new GameInvalidException('Game strike inventory item is unavailable');
        }
    }
}
