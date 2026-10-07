<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор выбора широкого удара. Готовые успех и урон не принимаются.
 */
final class GameWideStrikeBody
{
    /**
     * Выбор атаки.
     *
     * @param array<string, mixed> $attack Тело.
     *
     * @return array{
     *     attacker: array{type: string, id: int},
     *     targets: list<array{type: string, id: int}>,
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
            'itemRuleCode',
            'profileIndex',
            'profileType',
            'targets',
        ]);
        $targets = $this->targets($attack['targets'] ?? null);
        $profileType = $attack['profileType'] ?? null;
        $profileIndex = $attack['profileIndex'] ?? null;
        $actionRuleCode = $attack['actionRuleCode'] ?? null;
        $itemRuleCode = $attack['itemRuleCode'] ?? null;
        $codes = is_string($actionRuleCode) && is_string($itemRuleCode);
        if (!is_string($profileType) || !is_int($profileIndex) || !$codes) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: attack');
        }

        return [
            'attacker' => $this->side($attack['attacker'] ?? null),
            'targets' => $targets,
            'actionRuleCode' => $actionRuleCode,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
        ];
    }

    /**
     * Выбор защиты. Порядок совпадает с целями атаки.
     *
     * @param array<string, mixed> $defense Тело.
     *
     * @return list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> Защиты.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function defense(array $defense): array
    {
        $this->assertKeys($defense, ['defenses']);
        $rows = $defense['defenses'] ?? null;
        if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense');
        }

        $choices = [];
        foreach ($rows as $row) {
            $choices[] = $this->oneDefense($row);
        }

        return $choices;
    }

    /**
     * Одна защита.
     *
     * @param mixed $row Элемент.
     *
     * @return array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int} Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function oneDefense(mixed $row): array
    {
        if (!is_array($row)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense');
        }

        $allowed = array_key_exists('blockItemRuleCode', $row)
            ? ['blockItemRuleCode', 'expectedSheetVersion', 'reaction']
            : ['expectedSheetVersion', 'reaction'];
        $this->assertKeys($row, $allowed);
        $reaction = $row['reaction'] ?? null;
        $blockItemRuleCode = $row['blockItemRuleCode'] ?? null;
        $expectedSheetVersion = $row['expectedSheetVersion'] ?? null;
        $block = $blockItemRuleCode === null || is_string($blockItemRuleCode);
        if (!is_string($reaction) || !is_int($expectedSheetVersion) || !$block) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense');
        }

        return [
            'reaction' => $reaction,
            'blockItemRuleCode' => $blockItemRuleCode,
            'expectedSheetVersion' => $expectedSheetVersion,
        ];
    }

    /**
     * Не меньше двух разных целей.
     *
     * @param mixed $targets Список.
     *
     * @return list<array{type: string, id: int}> Цели.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function targets(mixed $targets): array
    {
        if (!is_array($targets) || !array_is_list($targets) || count($targets) < 2) {
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
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: wide strike');
        }
    }
}
