<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\ActionStrengthOp;

/**
 * Прибавки action_strength, схлопнутые внутри источника.
 */
final class ItemModifierOperationActions
{
    /**
     * Прибавки урона по блоку оружия или щита.
     *
     * @param array<int, ItemModifierSpec> $modifiers Модификаторы.
     * @param array<int, string> $keywordCodes Признаки.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public static function collect(array $modifiers, array $keywordCodes): array
    {
        $actions = [];
        foreach (self::grouped($modifiers, $keywordCodes) as $group) {
            $actions[] = self::collapsed($group);
        }

        return $actions;
    }

    /**
     * Группы одной прибавки.
     *
     * @param array<int, ItemModifierSpec> $modifiers Модификаторы.
     * @param array<int, string> $keywordCodes Признаки.
     *
     * @return array<string, array<int, array<string, mixed>>> Группы.
     */
    private static function grouped(array $modifiers, array $keywordCodes): array
    {
        $grouped = [];
        $unique = 0;
        foreach ($modifiers as $modifier) {
            foreach ($modifier->getOperations() as $operation) {
                $grouped = self::push($grouped, $operation, $keywordCodes, $unique);
            }
        }

        return $grouped;
    }

    /**
     * Кладёт операцию в группы. Пустой источник уникален.
     *
     * @param array<string, array<int, array<string, mixed>>> $grouped Группы.
     * @param ItemModifierOperation $operation Операция.
     * @param array<int, string> $keywordCodes Признаки.
     * @param int $unique Номер пустого источника.
     *
     * @return array<string, array<int, array<string, mixed>>> Группы.
     */
    private static function push(
        array $grouped,
        ItemModifierOperation $operation,
        array $keywordCodes,
        int &$unique,
    ): array {
        $op = $operation->getOp();
        if (
            !$op instanceof ActionStrengthOp
            || !ItemModifierOperationWhen::matches($operation->getWhen(), $keywordCodes)
        ) {
            return $grouped;
        }

        $source = $operation->getSourceCode();
        $key = self::key($op, $source, $unique);
        $unique += 1;
        foreach (ItemModifierOperationWhen::parts($operation->getWhen()) as $part) {
            $grouped = self::pushPart($grouped, $part, $key, $op, $source);
        }

        return $grouped;
    }

    /**
     * Одна строка блока. Доспех прибавку урона не получает.
     *
     * @param array<string, array<int, array<string, mixed>>> $grouped Группы.
     * @param string $part Блок.
     * @param string $key Ключ схлопывания.
     * @param ActionStrengthOp $op Ветка.
     * @param string|null $source Код источника.
     *
     * @return array<string, array<int, array<string, mixed>>> Группы.
     */
    private static function pushPart(
        array $grouped,
        string $part,
        string $key,
        ActionStrengthOp $op,
        ?string $source,
    ): array {
        if ($part === 'armor') {
            return $grouped;
        }

        $grouped[$part . $key][] = [
            'part' => $part,
            'field' => $op->getField(),
            'profiles' => $op->getProfiles(),
            'damageTypes' => $op->getDamageTypeCodes(),
            'source' => $source,
            'delta' => $op->getDelta(),
        ];

        return $grouped;
    }

    /**
     * Ключ схлопывания. Пустой источник уникален.
     *
     * @param ActionStrengthOp $op Ветка.
     * @param string|null $source Код или null.
     * @param int $unique Номер пустого источника.
     *
     * @return string Ключ.
     */
    private static function key(ActionStrengthOp $op, ?string $source, int $unique): string
    {
        $sourceKey = $source ?? "\0" . $unique;

        return $sourceKey . '|' . $op->getField() . '|' . implode(',', $op->getProfiles());
    }

    /**
     * Одна строка после схлопывания слагаемых.
     *
     * @param array<int, array<string, mixed>> $group Строки одного ключа.
     *
     * @return array<string, mixed> Строка.
     */
    private static function collapsed(array $group): array
    {
        $first = $group[0];
        $first['delta'] = ItemModifierOperationNumbers::collapseSource(self::deltas($group))['delta'];

        return $first;
    }

    /**
     * Слагаемые группы для общего схлопывания.
     *
     * @param array<int, array<string, mixed>> $group Строки.
     *
     * @return array<int, array{factor: null, delta: int, size: int}> Вклады.
     */
    private static function deltas(array $group): array
    {
        $deltas = [];
        foreach ($group as $row) {
            $deltas[] = ['factor' => null, 'delta' => $row['delta'], 'size' => 0];
        }

        return $deltas;
    }
}
