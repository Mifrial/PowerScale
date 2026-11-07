<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Payload эффектов реакции: поддержка и прикрытие.
 */
final class GameActionEffectInput
{
    /**
     * Payload эффекта support.
     *
     * @param array<string, mixed> $context Контекст реакции.
     *
     * @return array{side: string, itemInventoryId: int, itemRuleCode: string, profileType: string} Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    public function supportInput(array $context): array
    {
        $support = is_array($context['support'] ?? null) ? $context['support'] : null;
        $side = is_array($support) ? ($support['side'] ?? null) : null;
        $itemInventoryId = is_array($support) ? ($support['itemInventoryId'] ?? null) : null;
        $itemRuleCode = is_array($support) ? ($support['itemRuleCode'] ?? null) : null;
        $profileType = is_array($support) ? ($support['profileType'] ?? null) : null;
        if (!in_array($side, ['attacker', 'defender'], true)) {
            throw new GameInvalidException('Game strike support is invalid');
        }

        if (!is_int($itemInventoryId) || $itemInventoryId < 1) {
            throw new GameInvalidException('Game strike support is invalid');
        }

        if (!is_string($itemRuleCode) || $itemRuleCode === '') {
            throw new GameInvalidException('Game strike support is invalid');
        }

        if (!in_array($profileType, ['strike', 'throw', 'shoot'], true)) {
            throw new GameInvalidException('Game strike support is invalid');
        }

        return [
            'side' => $side,
            'itemInventoryId' => $itemInventoryId,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
        ];
    }

    /**
     * Payload эффекта cover_ally.
     *
     * @param array<string, mixed> $context Контекст реакции.
     *
     * @return array{reaction: string, blockItemInventoryId: int, blockItemProfileIndex: int, blockItemRuleCode: string} Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    public function coverInput(array $context): array
    {
        $cover = is_array($context['cover'] ?? null) ? $context['cover'] : null;
        $reaction = is_array($cover) ? ($cover['reaction'] ?? null) : null;
        $inventoryId = is_array($cover) ? ($cover['blockItemInventoryId'] ?? null) : null;
        $profileIndex = is_array($cover) ? ($cover['blockItemProfileIndex'] ?? null) : null;
        $ruleCode = is_array($cover) ? ($cover['blockItemRuleCode'] ?? null) : null;
        if (!is_string($reaction) || $reaction === '') {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        if (!is_int($inventoryId) || !is_int($profileIndex) || !is_string($ruleCode)) {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        return [
            'reaction' => $reaction,
            'blockItemInventoryId' => $inventoryId,
            'blockItemProfileIndex' => $profileIndex,
            'blockItemRuleCode' => $ruleCode,
        ];
    }
}
