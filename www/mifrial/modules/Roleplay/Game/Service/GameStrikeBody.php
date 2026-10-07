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
     *     itemRuleCode: string,
     *     profileType: string,
     *     profileIndex: int
     * } Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function attack(array $attack): array
    {
        $this->assertKeys($attack, [
            'actionRuleCode',
            'attacker',
            'defender',
            'itemRuleCode',
            'profileIndex',
            'profileType',
        ]);
        $attacker = $this->side($attack['attacker'] ?? null);
        $defender = $this->side($attack['defender'] ?? null);
        $profileType = $attack['profileType'] ?? null;
        $profileIndex = $attack['profileIndex'] ?? null;
        $actionRuleCode = $attack['actionRuleCode'] ?? null;
        $itemRuleCode = $attack['itemRuleCode'] ?? null;
        $codes = is_string($actionRuleCode) && is_string($itemRuleCode);
        if (!is_string($profileType) || !is_int($profileIndex) || !$codes) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }

        return [
            'attacker' => $attacker,
            'defender' => $defender,
            'actionRuleCode' => $actionRuleCode,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
        ];
    }

    /**
     * Выбор защиты.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return array{reaction: string, blockItemRuleCode: string|null} Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function defense(array $defense): array
    {
        $allowed = array_key_exists('blockItemRuleCode', $defense)
            ? ['blockItemRuleCode', 'reaction']
            : ['reaction'];
        $this->assertKeys($defense, $allowed);
        $reaction = $defense['reaction'] ?? null;
        $blockItemRuleCode = $defense['blockItemRuleCode'] ?? null;
        if (!is_string($reaction) || ($blockItemRuleCode !== null && !is_string($blockItemRuleCode))) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense');
        }

        return ['reaction' => $reaction, 'blockItemRuleCode' => $blockItemRuleCode];
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
