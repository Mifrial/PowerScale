<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Контекст ответа защитой.
 */
final class GameRunActionDefenseBody
{
    /**
     * Создаёт разбор защиты.
     *
     * @param GameRunActionFields $fields Общие проверки.
     *
     * @return void
     */
    public function __construct(private readonly GameRunActionFields $fields)
    {
    }

    /**
     * Контекст защиты.
     *
     * @param array<string, mixed> $context Контекст.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(array $context): array
    {
        $this->fields->assertKeys($context, ['defenses'], 'defense context');
        $rows = $context['defenses'] ?? null;
        if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense context');
        }

        $defenses = [];
        $seen = [];
        foreach ($rows as $row) {
            $defense = $this->row($row);
            $this->remember($seen, $defense);
            $defenses[] = $defense;
        }

        return ['defenses' => $defenses];
    }

    /**
     * Запоминает цель защиты и отвергает повтор.
     *
     * @param array<string, bool> $seen Уже виденные цели.
     * @param array<string, mixed> $defense Защита.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function remember(array &$seen, array $defense): void
    {
        $key = $this->targetKey($defense['target'] ?? null);
        if ($key === '') {
            return;
        }

        if (isset($seen[$key])) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense target');
        }

        $seen[$key] = true;
    }

    /**
     * Ключ цели защиты.
     *
     * @param mixed $target Цель.
     *
     * @return string Ключ или пустая строка.
     */
    private function targetKey(mixed $target): string
    {
        if (!is_array($target)) {
            return '';
        }

        $type = $target['type'] ?? null;
        $id = $target['id'] ?? null;

        return is_string($type) && is_int($id) ? $type . ':' . $id : '';
    }

    /**
     * Строка защиты.
     *
     * @param mixed $row Элемент.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function row(mixed $row): array
    {
        if (!is_array($row)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        $this->fields->assertKeys($row, $this->keys($row), 'defense row');
        $reaction = $row['reaction'] ?? null;
        $expected = $row['expectedSheetVersion'] ?? null;
        $inventoryId = $row['blockItemInventoryId'] ?? null;
        $profileIndex = $row['blockItemProfileIndex'] ?? null;
        $ruleCode = $row['blockItemRuleCode'] ?? null;
        $turn = $row['turn'] ?? false;
        $spendConcentration = $row['spendConcentration'] ?? 0;
        $target = array_key_exists('target', $row) ? $this->fields->side($row['target']) : null;
        $this->assertRow($reaction, $expected, $inventoryId, $profileIndex, $ruleCode, $turn, $spendConcentration);
        $choice = $this->choice($target, $reaction, $inventoryId, $profileIndex, $ruleCode, $expected, $turn);
        if (array_key_exists('spendConcentration', $row)) {
            $choice['spendConcentration'] = $spendConcentration;
        }

        return $choice;
    }

    /**
     * Выбор защиты.
     *
     * @param array{type: string, id: int}|null $target Цель.
     * @param mixed $reaction Реакция.
     * @param mixed $inventoryId Строка предмета блока.
     * @param mixed $profileIndex Индекс профиля блока.
     * @param mixed $ruleCode Код предмета блока.
     * @param mixed $expected Версия листа.
     * @param mixed $turn Ход.
     *
     * @return array<string, mixed> Выбор.
     */
    private function choice(
        ?array $target,
        mixed $reaction,
        mixed $inventoryId,
        mixed $profileIndex,
        mixed $ruleCode,
        mixed $expected,
        mixed $turn,
    ): array {
        return [
            'target' => $target,
            'reaction' => $reaction,
            'blockItemInventoryId' => $inventoryId,
            'blockItemProfileIndex' => $profileIndex,
            'blockItemRuleCode' => $ruleCode,
            'expectedSheetVersion' => $expected,
            'turn' => $turn,
        ];
    }

    /**
     * Ключи строки защиты.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return list<string> Ключи.
     */
    private function keys(array $row): array
    {
        $allowed = ['expectedSheetVersion', 'reaction'];
        foreach (['target', 'turn', 'spendConcentration'] as $key) {
            if (array_key_exists($key, $row)) {
                $allowed[] = $key;
            }
        }

        if (
            array_key_exists('blockItemInventoryId', $row)
            || array_key_exists('blockItemProfileIndex', $row)
        ) {
            $allowed[] = 'blockItemInventoryId';
            $allowed[] = 'blockItemProfileIndex';
            if (array_key_exists('blockItemRuleCode', $row)) {
                $allowed[] = 'blockItemRuleCode';
            }
        } elseif (array_key_exists('blockItemRuleCode', $row)) {
            $allowed[] = 'blockItemRuleCode';
        }

        return $allowed;
    }

    /**
     * Проверяет поля строки защиты.
     *
     * @param mixed $reaction Реакция.
     * @param mixed $expected Версия листа.
     * @param mixed $inventoryId Строка предмета блока.
     * @param mixed $profileIndex Индекс профиля блока.
     * @param mixed $ruleCode Код предмета блока.
     * @param mixed $turn Ход.
     * @param mixed $spendConcentration Трата концентрации.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertRow(
        mixed $reaction,
        mixed $expected,
        mixed $inventoryId,
        mixed $profileIndex,
        mixed $ruleCode,
        mixed $turn,
        mixed $spendConcentration,
    ): void {
        $this->assertReaction($reaction, $turn);
        if (!is_int($expected)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        if (!is_int($spendConcentration) || $spendConcentration < 0) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        if (!$this->isBlock($inventoryId, $profileIndex, $ruleCode)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        if ($reaction === 'block' && (!is_int($inventoryId) || !is_int($profileIndex))) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }
    }

    /**
     * Проверяет реакцию и ход.
     *
     * @param mixed $reaction Реакция.
     * @param mixed $turn Ход.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertReaction(mixed $reaction, mixed $turn): void
    {
        if (!is_string($reaction) || $reaction === '') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        if (!is_bool($turn)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }

        if ($turn && $reaction === 'ignore') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense row');
        }
    }

    /**
     * Поля блока пусты или нужного типа.
     *
     * @param mixed $inventoryId Строка предмета.
     * @param mixed $profileIndex Индекс профиля.
     * @param mixed $ruleCode Код предмета.
     *
     * @return bool Истина, если поля допустимы.
     */
    private function isBlock(mixed $inventoryId, mixed $profileIndex, mixed $ruleCode): bool
    {
        $valid = $inventoryId === null || is_int($inventoryId);
        if ($profileIndex !== null && !is_int($profileIndex)) {
            $valid = false;
        }

        if ($ruleCode !== null && !is_string($ruleCode)) {
            $valid = false;
        }

        return $valid;
    }
}
