<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Списания удара: ресурсы карточки, реакция и предмет.
 */
final class GameStrikeSpends
{
    /**
     * Принимает срезы, ресурсы и списание предмета.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameActionResourceResolver $resourceResolver Ресурсы действия.
     * @param GameStrikeInventorySpend $inventorySpend Списание предмета.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameActionResourceResolver $resourceResolver,
        private readonly GameStrikeInventorySpend $inventorySpend,
    ) {
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
     * @param bool $allowZeroChosen Разрешить нулевой выбор.
     *
     * @return array<int, ResourceSpend> Native spends.
     *
     * @throws GameInvalidException If action is missing or ambiguous.
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
            $this->slices->slice($spaceId, $rulesRevision),
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
            $this->slices->slice($spaceId, $rulesRevision),
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
            $this->slices->slice($spaceId, $rulesRevision),
            $sheet,
            $spends,
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
}
