<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Resource;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceLimitBonus;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Разбирает и сериализует native-границу хранения ресурсов.
 */
final class CharacterResourceStorage
{
    private readonly CharacterResourceArithmetic $arithmetic;

    private readonly CharacterResourceRowReader $reader;

    /**
     * Создаёт границу хранения ресурсов.
     *
     * @return void
     */
    public function __construct()
    {
        $this->arithmetic = new CharacterResourceArithmetic();
        $this->reader = new CharacterResourceRowReader();
    }

    /**
     * Разбирает сохранённые строки по live-правилам.
     *
     * @param array<mixed> $rows Native rows.
     * @param CharacterRuleSlice $slice Live rule slice.
     *
     * @return array<int, ResourceRow> Validated rows.
     *
     * @throws CharacterInvalidException If the storage shape is invalid.
     */
    public function parseRows(array $rows, CharacterRuleSlice $slice): array
    {
        if (!array_is_list($rows)) {
            throw new CharacterInvalidException('Resource rows must be a list');
        }

        $parsed = [];
        $seen = [];
        foreach ($rows as $row) {
            $resourceRow = $this->reader->readRow($row, $slice);
            $code = $resourceRow->getRuleCode();
            if (isset($seen[$code])) {
                throw new CharacterInvalidException('Resource row is duplicated');
            }

            $seen[$code] = true;
            $parsed[] = $resourceRow;
        }

        return $parsed;
    }

    /**
     * Сериализует проверенные строки в native-форму.
     *
     * @param array<int, ResourceRow> $rows Validated rows.
     *
     * @return array<int, array{ruleCode: string, current: int|array{base: int, size: int}}> Native rows.
     *
     * @throws CharacterInvalidException If a row has an invalid runtime type.
     */
    public function serializeRows(array $rows): array
    {
        $serialized = [];
        $seen = [];
        foreach ($rows as $row) {
            $code = $row->getRuleCode();
            if (isset($seen[$code])) {
                throw new CharacterInvalidException('Resource row is duplicated');
            }

            $seen[$code] = true;
            $serialized[] = $this->serializeRow($row);
        }

        return $serialized;
    }

    /**
     * Готовит атомарное списание по native-строкам.
     *
     * @param array<int, ResourceRow> $rows Проверенные строки.
     * @param CharacterRuleSlice $slice Live rule slice.
     * @param array<int, ResourceSpend> $spends Server-resolved spends.
     *
     * @return array<int, ResourceRow> Rows after spend.
     *
     * @throws CharacterInvalidException If a resource or amount is invalid.
     */
    public function spendRows(
        array $rows,
        CharacterRuleSlice $slice,
        array $spends,
    ): array {
        $validated = [];
        foreach ($spends as $spend) {
            $spec = $this->reader->resourceSpec($slice, $spend->getResourceCode());
            $amount = $this->reader->readValue($spend->getAmount()->toNative(), $spec);
            $validated[] = new ResourceSpend($spend->getResourceCode(), $amount);
        }

        return $this->arithmetic->spendRows($rows, $validated);
    }

    /**
     * Ставит current строки с клампом 0..effective limit.
     *
     * @param array<int, ResourceRow> $rows Строки.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код ресурса.
     * @param mixed $current Native current.
     *
     * @return array<int, ResourceRow> Строки.
     *
     * @throws CharacterInvalidException Если ресурса нет или форма.
     */
    public function setCurrentRows(
        array $rows,
        CharacterRuleSlice $slice,
        string $code,
        mixed $current,
    ): array {
        $spec = $this->reader->resourceSpec($slice, $code);
        $found = false;
        $next = [];
        foreach ($rows as $row) {
            if ($row->getRuleCode() !== $code) {
                $next[] = $row;
                continue;
            }

            $found = true;
            $parsed = $this->reader->readValue($this->floorNative($current, $spec), $spec);
            $next[] = new ResourceRow($code, $parsed, $row->getBase(), $row->getBonuses());
        }

        if (!$found) {
            throw new CharacterInvalidException('Resource row is missing');
        }

        return $next;
    }

    /**
     * Проверяет достаточность всех подготовленных трат без записи.
     *
     * @param array<int, ResourceRow> $rows Проверенные строки.
     * @param CharacterRuleSlice $slice Live rule slice.
     * @param array<int, ResourceSpend> $spends Server-resolved spends.
     *
     * @return bool true, если все траты допустимы по current.
     *
     * @throws CharacterInvalidException Если форма невалидна.
     */
    public function canSpendRows(
        array $rows,
        CharacterRuleSlice $slice,
        array $spends,
    ): bool {
        try {
            $this->spendRows($rows, $slice, $spends);
        } catch (CharacterInvalidException $exception) {
            if ($exception->getMessage() === 'Resource current is insufficient') {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    /**
     * Сериализует одну проверенную строку.
     *
     * @param ResourceRow $row Validated row.
     *
     * @return array{ruleCode: string, current: int|array{base: int, size: int}} Native row.
     */
    private function serializeRow(ResourceRow $row): array
    {
        $entry = [
            'ruleCode' => $row->getRuleCode(),
            'current' => $row->getCurrent()->toNative(),
        ];
        $base = $row->getBase();
        if (is_int($base)) {
            $entry['base'] = $base;
        } elseif ($base instanceof DimensionalNumber) {
            $entry['base'] = ['base' => $base->getBase(), 'size' => $base->getSize()];
        }

        $bonuses = $this->serializeBonuses($row->getBonuses());
        if ($bonuses !== []) {
            $entry['bonuses'] = $bonuses;
        }

        return $entry;
    }

    /**
     * Сериализует вклады в лимит без нулевых.
     *
     * @param array<int, ResourceLimitBonus> $bonuses Вклады.
     *
     * @return array<int, array{sourceRuleCode: string|null, delta: int}> Native вклады.
     */
    private function serializeBonuses(array $bonuses): array
    {
        $serialized = [];
        foreach ($bonuses as $bonus) {
            if ($bonus->getDelta() !== 0) {
                $serialized[] = $bonus->toNative();
            }
        }

        return $serialized;
    }

    /**
     * Нижняя граница 0 до разбора native.
     *
     * @param mixed $current Native.
     * @param ResourceSpec $spec Карточка.
     *
     * @return mixed Native ≥ 0.
     */
    private function floorNative(mixed $current, ResourceSpec $spec): mixed
    {
        if (!$spec->isDimensional() && is_int($current)) {
            return max(0, $current);
        }

        if (is_array($current) && is_int($current['base'] ?? null)) {
            return [...$current, 'base' => max(0, $current['base'])];
        }

        return $current;
    }
}
