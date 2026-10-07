<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;

/**
 * Применяет operations модификатора к предмету. Эффекты с подписью не читает.
 */
final class ItemModifierOperations
{
    /**
     * Собирает новый spec. Входной предмет не меняется.
     *
     * @param ItemSpec $item Предмет.
     * @param array<int, ItemModifierSpec> $modifiers Уже посаженные модификаторы.
     * @param array<int, string> $keywordCodes Признаки предмета.
     *
     * @return ItemSpec Тот же объект, если чисел нет.
     */
    public static function apply(ItemSpec $item, array $modifiers, array $keywordCodes): ItemSpec
    {
        $entries = self::entries($modifiers, $keywordCodes);
        $actions = ItemModifierOperationActions::collect($modifiers, $keywordCodes);
        if ($entries === [] && $actions === []) {
            return $item;
        }

        return ItemModifierOperationItems::write($item, ItemModifierOperationNumbers::collapse($entries), $actions);
    }

    /**
     * Вклады операций, чьё условие выполнено.
     *
     * @param array<int, ItemModifierSpec> $modifiers Модификаторы.
     * @param array<int, string> $keywordCodes Признаки.
     *
     * @return array<int, array<string, int|float|string|null>> Вклады.
     */
    private static function entries(array $modifiers, array $keywordCodes): array
    {
        $entries = [];
        $unique = 0;
        foreach ($modifiers as $modifier) {
            foreach ($modifier->getOperations() as $operation) {
                $entries = self::push($entries, $operation, $keywordCodes, $unique);
            }
        }

        return $entries;
    }

    /**
     * Добавляет вклады одной операции.
     *
     * @param array<int, array<string, int|float|string|null>> $entries Вклады.
     * @param ItemModifierOperation $operation Операция.
     * @param array<int, string> $keywordCodes Признаки.
     * @param int $unique Номер пустого источника.
     *
     * @return array<int, array<string, int|float|string|null>> Вклады.
     */
    private static function push(
        array $entries,
        ItemModifierOperation $operation,
        array $keywordCodes,
        int &$unique,
    ): array {
        if (!ItemModifierOperationWhen::matches($operation->getWhen(), $keywordCodes)) {
            return $entries;
        }

        $source = $operation->getSourceCode() ?? "\0" . $unique;
        $unique += 1;
        foreach (ItemModifierOperationWhen::targets($operation) as $target) {
            $entries[] = ItemModifierOperationNumbers::entry($target, $source, $operation->getOp());
        }

        return $entries;
    }
}
