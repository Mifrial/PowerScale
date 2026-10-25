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
     *     ammunitionInventoryId?: int,
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

        if (array_key_exists('ammunitionInventoryId', $attack)) {
            $allowed[] = 'ammunitionInventoryId';
        }

        if (array_key_exists('instanceIndex', $attack)) {
            $allowed[] = 'instanceIndex';
        }

        $this->assertKeys($attack, $allowed);
        $attacker = $this->side($attack['attacker'] ?? null);
        $defender = $this->side($attack['defender'] ?? null);
        $profileType = $attack['profileType'] ?? null;
        $profileIndex = $attack['profileIndex'] ?? null;
        $actionRuleCode = $attack['actionRuleCode'] ?? null;
        $itemRuleCode = $attack['itemRuleCode'] ?? null;
        $itemInventoryId = $attack['itemInventoryId'] ?? null;
        $ammunitionInventoryId = $attack['ammunitionInventoryId'] ?? null;
        $instanceIndex = $attack['instanceIndex'] ?? 0;
        $chosenAmounts = $attack['chosenAmounts'] ?? [];
        $codes = is_string($actionRuleCode) && is_string($itemRuleCode);
        if (
            !is_int($itemInventoryId)
            || !is_int($instanceIndex)
            || $instanceIndex < 0
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !$codes
            || !is_array($chosenAmounts)
            || ($profileType === 'shoot'
                ? !is_int($ammunitionInventoryId)
                : $ammunitionInventoryId !== null)
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
            ...($profileType === 'shoot' ? ['ammunitionInventoryId' => $ammunitionInventoryId] : []),
            'instanceIndex' => $instanceIndex,
            'chosenAmounts' => $chosenAmounts,
        ];
    }

    /**
     * Выбор защиты.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function defense(array $defense): array
    {
        $allowed = ['reaction'];
        if (
            array_key_exists('blockItemInventoryId', $defense)
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
        if (
            !is_string($reaction)
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
     * Выбор атаки по нескольким целям.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function wideAttack(array $attack): array
    {
        $allowed = [
            'actionRuleCode',
            'attacker',
            'itemInventoryId',
            'itemRuleCode',
            'profileIndex',
            'profileType',
            'targets',
        ];
        if (array_key_exists('chosenAmounts', $attack)) {
            $allowed[] = 'chosenAmounts';
        }

        if (array_key_exists('ammunitionInventoryId', $attack)) {
            $allowed[] = 'ammunitionInventoryId';
        }

        if (array_key_exists('instanceIndex', $attack)) {
            $allowed[] = 'instanceIndex';
        }

        $this->assertKeys($attack, $allowed, 'wide strike');
        $targets = $this->targets($attack['targets'] ?? null);
        $profileType = $attack['profileType'] ?? null;
        $profileIndex = $attack['profileIndex'] ?? null;
        $actionRuleCode = $attack['actionRuleCode'] ?? null;
        $itemInventoryId = $attack['itemInventoryId'] ?? null;
        $itemRuleCode = $attack['itemRuleCode'] ?? null;
        $ammunitionInventoryId = $attack['ammunitionInventoryId'] ?? null;
        $instanceIndex = $attack['instanceIndex'] ?? 0;
        $chosenAmounts = $attack['chosenAmounts'] ?? [];
        $codes = is_string($actionRuleCode) && is_string($itemRuleCode);
        if (
            !is_int($itemInventoryId)
            || !is_int($instanceIndex)
            || $instanceIndex < 0
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !$codes
            || !is_array($chosenAmounts)
            || ($profileType === 'shoot'
                ? !is_int($ammunitionInventoryId)
                : $ammunitionInventoryId !== null)
        ) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }

        return [
            'attacker' => $this->side($attack['attacker'] ?? null),
            'targets' => $targets,
            'actionRuleCode' => $actionRuleCode,
            'itemInventoryId' => $itemInventoryId,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
            ...($profileType === 'shoot' ? ['ammunitionInventoryId' => $ammunitionInventoryId] : []),
            'instanceIndex' => $instanceIndex,
            'chosenAmounts' => $chosenAmounts,
        ];
    }

    /**
     * Выбор защиты по нескольким целям.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return list<array<string, mixed>> Защиты.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function wideDefense(array $defense): array
    {
        $this->assertKeys($defense, ['defenses'], 'wide strike');
        $rows = $defense['defenses'] ?? null;
        if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense');
        }

        $choices = [];
        $seen = [];
        foreach ($rows as $row) {
            $choice = $this->wideDefenseRow($row);
            $key = $choice['target']['type'] . ':' . $choice['target']['id'];
            if (isset($seen[$key])) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense target');
            }

            $seen[$key] = true;
            $choices[] = $choice;
        }

        return $choices;
    }

    /**
     * Одна защита широкого удара.
     *
     * @param mixed $row Элемент.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function wideDefenseRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        $allowed = ['expectedSheetVersion', 'reaction', 'target'];
        if (
            array_key_exists('blockItemInventoryId', $row)
            || array_key_exists('blockItemProfileIndex', $row)
        ) {
            $allowed = [
                'blockItemInventoryId',
                'blockItemProfileIndex',
                'expectedSheetVersion',
                'reaction',
                'target',
            ];
            if (array_key_exists('blockItemRuleCode', $row)) {
                $allowed[] = 'blockItemRuleCode';
            }
        } elseif (array_key_exists('blockItemRuleCode', $row)) {
            $allowed = ['blockItemRuleCode', 'expectedSheetVersion', 'reaction', 'target'];
        }

        $this->assertKeys($row, $allowed, 'wide strike');
        $target = $this->side($row['target'] ?? null);
        $reaction = $row['reaction'] ?? null;
        $blockItemInventoryId = $row['blockItemInventoryId'] ?? null;
        $blockItemProfileIndex = $row['blockItemProfileIndex'] ?? null;
        $blockItemRuleCode = $row['blockItemRuleCode'] ?? null;
        $expectedSheetVersion = $row['expectedSheetVersion'] ?? null;
        $block = ($blockItemInventoryId === null || is_int($blockItemInventoryId))
            && ($blockItemProfileIndex === null || is_int($blockItemProfileIndex))
            && ($blockItemRuleCode === null || is_string($blockItemRuleCode));
        if (
            !is_string($reaction)
            || !is_int($expectedSheetVersion)
            || !$block
            || ($reaction === 'block'
                && (!is_int($blockItemInventoryId) || !is_int($blockItemProfileIndex)))
        ) {
            throw new ActionException(
                'INVALID_PARAMS',
                'Invalid parameter: defense values ' . json_encode($row),
            );
        }

        return [
            'target' => $target,
            'reaction' => $reaction,
            'blockItemInventoryId' => $blockItemInventoryId,
            'blockItemProfileIndex' => $blockItemProfileIndex,
            'blockItemRuleCode' => $blockItemRuleCode,
            'expectedSheetVersion' => $expectedSheetVersion,
        ];
    }

    /**
     * Не меньше одной разной цели.
     *
     * @param mixed $targets Список.
     *
     * @return list<array{type: string, id: int}> Цели.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function targets(mixed $targets): array
    {
        if (!is_array($targets) || !array_is_list($targets) || count($targets) < 1) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: targets');
        }

        $sides = [];
        foreach ($targets as $target) {
            $side = $this->side($target);
            if (in_array($side, $sides, true)) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: targets');
            }

            $sides[] = $side;
        }

        return $sides;
    }

    /**
     * Ровно названные ключи.
     *
     * @param array<string, mixed> $body Тело.
     * @param list<string> $allowed Ключи.
     * @param string $subject Подлежащее сообщения.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertKeys(array $body, array $allowed, string $subject = 'strike'): void
    {
        $keys = array_keys($body);
        sort($keys);
        $expected = $allowed;
        sort($expected);
        if ($keys !== $expected) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $subject);
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
