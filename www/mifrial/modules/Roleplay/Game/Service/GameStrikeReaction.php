<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Разбор строки реакции: блок, уклонение, парирование.
 */
final class GameStrikeReaction
{
    /**
     * Создаёт разбор реакции.
     *
     * @param GameStrikeBodyFields $fields Проверки формы.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeBodyFields $fields,
    ) {
    }

    /**
     * Реакция одной защиты.
     *
     * @param array<string, mixed> $row Тело.
     * @param list<string> $base Базовые ключи.
     * @param bool $wide Широкий удар.
     * @param string $invalid Текст ошибки значений.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function choice(array $row, array $base, bool $wide, string $invalid): array
    {
        $allowed = $this->fields->reactionKeys($row, $base);
        $this->fields->assertKeys($row, $allowed, $wide ? 'wide strike' : 'strike');
        $target = $wide ? $this->fields->side($row['target'] ?? null) : null;
        $values = $this->values($row);
        $this->assert($values, $wide, $invalid);

        return $this->build($row, $values, $target, $wide);
    }

    /**
     * Значения реакции.
     *
     * @param array<string, mixed> $row Тело.
     *
     * @return array<string, mixed> Значения.
     */
    private function values(array $row): array
    {
        return [
            'reaction' => $row['reaction'] ?? null,
            'blockItemInventoryId' => $row['blockItemInventoryId'] ?? null,
            'blockItemProfileIndex' => $row['blockItemProfileIndex'] ?? null,
            'blockItemRuleCode' => $row['blockItemRuleCode'] ?? null,
            'expectedSheetVersion' => $row['expectedSheetVersion'] ?? null,
            'turn' => $row['turn'] ?? false,
            'spendConcentration' => $row['spendConcentration'] ?? 0,
        ];
    }

    /**
     * Проверяет значения реакции.
     *
     * @param array<string, mixed> $values Значения.
     * @param bool $wide Широкий удар.
     * @param string $invalid Текст ошибки.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assert(array $values, bool $wide, string $invalid): void
    {
        $this->assertReaction($values, $invalid);
        $this->assertBlock($values, $invalid);
        if ($wide) {
            $this->assertWide($values, $invalid);
        }
    }

    /**
     * Проверяет реакцию, ход и концентрацию.
     *
     * @param array<string, mixed> $values Значения.
     * @param string $invalid Текст ошибки.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertReaction(array $values, string $invalid): void
    {
        if (!is_string($values['reaction']) || !is_bool($values['turn'])) {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }

        if ($values['turn'] === true && $values['reaction'] === 'ignore') {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }

        if (!is_int($values['spendConcentration']) || $values['spendConcentration'] < 0) {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }
    }

    /**
     * Проверяет предмет блока.
     *
     * @param array<string, mixed> $values Значения.
     * @param string $invalid Текст ошибки.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertBlock(array $values, string $invalid): void
    {
        if ($values['blockItemInventoryId'] !== null && !is_int($values['blockItemInventoryId'])) {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }

        if ($values['blockItemProfileIndex'] !== null && !is_int($values['blockItemProfileIndex'])) {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }

        if ($values['blockItemRuleCode'] !== null && !is_string($values['blockItemRuleCode'])) {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }

        if ($this->missingBlock($values)) {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }
    }

    /**
     * Блок без предмета.
     *
     * @param array<string, mixed> $values Значения.
     *
     * @return bool Признак.
     */
    private function missingBlock(array $values): bool
    {
        return $values['reaction'] === 'block'
            && (!is_int($values['blockItemInventoryId']) || !is_int($values['blockItemProfileIndex']));
    }

    /**
     * Проверяет версию листа широкой защиты.
     *
     * @param array<string, mixed> $values Значения.
     * @param string $invalid Текст ошибки.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertWide(array $values, string $invalid): void
    {
        if (!is_int($values['expectedSheetVersion'])) {
            throw new ActionException('INVALID_PARAMS', $invalid);
        }
    }

    /**
     * Собирает выбор реакции.
     *
     * @param array<string, mixed> $row Тело.
     * @param array<string, mixed> $values Значения.
     * @param array{type: string, id: int}|null $target Цель широкой защиты.
     * @param bool $wide Широкий удар.
     *
     * @return array<string, mixed> Выбор.
     */
    private function build(array $row, array $values, ?array $target, bool $wide): array
    {
        $choice = [
            'reaction' => $values['reaction'],
            'blockItemInventoryId' => $values['blockItemInventoryId'],
            'blockItemProfileIndex' => $values['blockItemProfileIndex'],
            'blockItemRuleCode' => $values['blockItemRuleCode'],
            'turn' => $values['turn'],
        ];
        if ($wide) {
            $choice = [
                'target' => $target,
                'reaction' => $values['reaction'],
                'blockItemInventoryId' => $values['blockItemInventoryId'],
                'blockItemProfileIndex' => $values['blockItemProfileIndex'],
                'blockItemRuleCode' => $values['blockItemRuleCode'],
                'expectedSheetVersion' => $values['expectedSheetVersion'],
                'turn' => $values['turn'],
            ];
        }

        if (array_key_exists('spendConcentration', $row)) {
            $choice['spendConcentration'] = $values['spendConcentration'];
        }

        return $choice;
    }
}
