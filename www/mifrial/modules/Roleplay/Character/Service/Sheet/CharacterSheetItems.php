<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterItemRecorded;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemStorage;

/**
 * Рантайм предметов-хранилищ в листе: `sheet.items`.
 *
 * Ключ — `inventory:<id>` строки инвентаря. Предельная мощь и Объём — из
 * сборки персонажа, занятый Объём считается по записям, мана живёт только
 * в листе: сборка её не знает и не переписывает.
 */
final class CharacterSheetItems
{
    private readonly CharacterItemRecorded $recorded;

    /**
     * Создаёт расчёт.
     *
     * @return void
     */
    public function __construct()
    {
        $this->recorded = new CharacterItemRecorded();
    }

    /**
     * Дописывает строки хранилищ в лист.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return array<string, mixed> Лист.
     */
    public function apply(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
    ): array {
        $items = $this->items($slice, $choices, is_array($sheet['items'] ?? null) ? $sheet['items'] : []);
        if ($items === []) {
            unset($sheet['items']);

            return $sheet;
        }

        $sheet['items'] = $items;

        return $sheet;
    }

    /**
     * Строки хранилищ.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $previous Предыдущий `sheet.items`.
     *
     * @return array<string, array<string, mixed>> Ключ → строка.
     */
    private function items(CharacterRuleSlice $slice, array $choices, array $previous): array
    {
        $items = [];
        foreach (is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $ruleCode = $row['ruleCode'] ?? null;
            if (!is_int($id) || $id <= 0 || !is_string($ruleCode) || $ruleCode === '') {
                continue;
            }

            $storage = $this->storageOf($slice, $ruleCode);
            if ($storage === null) {
                continue;
            }

            $power = $this->powerOf($row, $storage);
            $volume = $this->volumeOf($row, $storage);
            if ($power === null || $volume === null) {
                continue;
            }

            $key = 'inventory:' . $id;
            $occupied = $this->recorded->occupied($slice, $this->payloadOf($row)[$storage->getRecordedParameter()] ?? [])['volume'];
            $limit = $this->recorded->manaLimit($volume, $occupied);
            $items[$key] = [
                'ruleCode' => $ruleCode,
                'inventoryId' => $id,
                'maxPower' => ['base' => $power[0], 'size' => $power[1]],
                'volume' => $volume,
                'occupied' => $occupied,
                'mana' => $this->manaOf($previous[$key] ?? null, $limit),
                'manaMax' => $limit,
            ];
        }

        return $items;
    }

    /**
     * Текущая мана: сохранённая в листе, иначе полная.
     *
     * @param mixed $row Предыдущая строка.
     * @param int $limit Лимит маны.
     *
     * @return int Мана.
     */
    private function manaOf(mixed $row, int $limit): int
    {
        $mana = is_array($row) ? ($row['mana'] ?? null) : null;
        if (!is_int($mana)) {
            return $limit;
        }

        return max(0, min($mana, $limit));
    }

    /**
     * Блок хранилища живого предмета.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код.
     *
     * @return ItemStorage|null Блок или null.
     */
    private function storageOf(CharacterRuleSlice $slice, string $ruleCode): ?ItemStorage
    {
        $rule = $slice->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof ItemSpec ? $spec->getStorage() : null;
    }

    /**
     * Предельная мощь строки.
     *
     * @param array<string, mixed> $row Строка инвентаря.
     * @param ItemStorage $storage Блок.
     *
     * @return array{0: int, 1: int}|null База и размер или null.
     */
    private function powerOf(array $row, ItemStorage $storage): ?array
    {
        $parameters = is_array($row['parameters'] ?? null) ? $row['parameters'] : [];
        $value = $parameters[$storage->getPowerParameter()] ?? null;
        if (!is_array($value)) {
            return null;
        }

        $base = $value['base'] ?? null;
        $size = $value['size'] ?? null;

        return is_int($base) && is_int($size) ? [$base, $size] : null;
    }

    /**
     * Объём строки.
     *
     * @param array<string, mixed> $row Строка инвентаря.
     * @param ItemStorage $storage Блок.
     *
     * @return int|null Объём или null.
     */
    private function volumeOf(array $row, ItemStorage $storage): ?int
    {
        $value = $this->payloadOf($row)[$storage->getVolumeParameter()] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * Payload строки.
     *
     * @param array<string, mixed> $row Строка инвентаря.
     *
     * @return array<string, mixed> Payload.
     */
    private function payloadOf(array $row): array
    {
        return is_array($row['payload'] ?? null) ? $row['payload'] : [];
    }
}
