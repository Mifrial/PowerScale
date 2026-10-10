<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор выбора удара. Готовый урон не принимается.
 */
final class GameStrikeBody
{
    /**
     * Выбор атаки.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array{
     *     attacker: array{type: string, id: int},
     *     defender: array{type: string, id: int},
     *     actionRuleCode: string,
     *     itemInventoryId: int,
     *     itemRuleCode: string,
     *     profileType: string,
     *     profileIndex: int,
     *     chosenAmounts: array<string, mixed>
     * } Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function attack(array $attack): array
    {
        $allowed = [
            'actionRuleCode',
            'attacker',
            'defender',
            'itemInventoryId',
            'itemRuleCode',
            'profileIndex',
            'profileType',
        ];
        if (array_key_exists('chosenAmounts', $attack)) {
            $allowed[] = 'chosenAmounts';
        }
        $this->assertKeys($attack, $allowed);
        $attacker = $this->side($attack['attacker'] ?? null);
        $defender = $this->side($attack['defender'] ?? null);
        $profileType = $attack['profileType'] ?? null;
        $profileIndex = $attack['profileIndex'] ?? null;
        $actionRuleCode = $attack['actionRuleCode'] ?? null;
        $itemRuleCode = $attack['itemRuleCode'] ?? null;
        $itemInventoryId = $attack['itemInventoryId'] ?? null;
        $chosenAmounts = $attack['chosenAmounts'] ?? [];
        $codes = is_string($actionRuleCode) && is_string($itemRuleCode);
        if (!is_int($itemInventoryId)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !$codes
            || !is_array($chosenAmounts)
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }

        return [
            'attacker' => $attacker,
            'defender' => $defender,
            'actionRuleCode' => $actionRuleCode,
            'itemInventoryId' => $itemInventoryId,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
            'chosenAmounts' => $chosenAmounts,
        ];
    }

    /**
     * Выбор защиты.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null} Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function defense(array $defense): array
    {
        $allowed = ['reaction'];
        if (array_key_exists('blockItemInventoryId', $defense)
            || array_key_exists('blockItemProfileIndex', $defense)
        ) {
            $allowed = [
                'blockItemInventoryId',
                'blockItemProfileIndex',
                'reaction',
            ];
            if (array_key_exists('blockItemRuleCode', $defense)) {
                $allowed[] = 'blockItemRuleCode';
            }
        } elseif (array_key_exists('blockItemRuleCode', $defense)) {
            $allowed = ['blockItemRuleCode', 'reaction'];
        }
        $this->assertKeys($defense, $allowed);
        $reaction = $defense['reaction'] ?? null;
        $blockItemInventoryId = $defense['blockItemInventoryId'] ?? null;
        $blockItemProfileIndex = $defense['blockItemProfileIndex'] ?? null;
        $blockItemRuleCode = $defense['blockItemRuleCode'] ?? null;
        if (!is_string($reaction)
            || ($blockItemInventoryId !== null && !is_int($blockItemInventoryId))
            || ($blockItemProfileIndex !== null && !is_int($blockItemProfileIndex))
            || ($blockItemRuleCode !== null && !is_string($blockItemRuleCode))
            || ($reaction === 'block'
                && (!is_int($blockItemInventoryId) || !is_int($blockItemProfileIndex)))
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense');
        }

        return [
            'reaction' => $reaction,
            'blockItemInventoryId' => $blockItemInventoryId,
            'blockItemProfileIndex' => $blockItemProfileIndex,
            'blockItemRuleCode' => $blockItemRuleCode,
        ];
    }

    /**
     * Ровно названные ключи.
     *
     * @param array<string, mixed> $body Тело.
     * @param list<string> $allowed Ключи.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertKeys(array $body, array $allowed): void
    {
        $keys = array_keys($body);
        sort($keys);
        $expected = $allowed;
        sort($expected);
        if ($keys !== $expected) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: strike');
        }
    }

    /**
     * Участник.
     *
     * @param mixed $side Объект.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function side(mixed $side): array
    {
        if (!is_array($side)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        $keys = array_keys($side);
        sort($keys);
        $type = $side['type'] ?? null;
        $id = $side['id'] ?? null;
        if ($keys !== ['id', 'type'] || !is_string($type) || !is_int($id)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: side');
        }

        return ['type' => $type, 'id' => $id];
    }
}
