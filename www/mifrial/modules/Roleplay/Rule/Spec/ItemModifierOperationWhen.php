<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierApplies;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierOperation;

/**
 * Условие операции и блоки spec, которые она выбирает.
 */
final class ItemModifierOperationWhen
{
    /**
     * Признак блока оружия в условии операции.
     */
    private const WEAPON = 'weapon';

    /**
     * Признак блока щита в условии операции.
     */
    private const SHIELD = 'shield-item';

    /**
     * Признак блока доспеха в условии операции.
     */
    private const ARMOR = 'armor-item';

    /**
     * Условие посадки по тройке кодов.
     *
     * @param ItemModifierApplies $when Условие.
     * @param array<int, string> $keywordCodes Признаки предмета.
     *
     * @return bool true, если операция входит.
     */
    public static function matches(ItemModifierApplies $when, array $keywordCodes): bool
    {
        $codes = array_fill_keys($keywordCodes, true);

        return self::passesNone($when, $codes)
            && self::passesAll($when, $codes)
            && self::passesAny($when, $codes);
    }

    /**
     * Поля, которые трогает операция.
     *
     * @param ItemModifierOperation $operation Операция.
     *
     * @return array<int, string> Ключи.
     */
    public static function targets(ItemModifierOperation $operation): array
    {
        $type = $operation->getOp()->getType();
        if ($type === 'min_action_cost' || $type === 'min_resource_cost') {
            return [];
        }

        if ($type === 'weight' || $type === 'block' || $type === 'action_strength') {
            return self::itemTarget($type);
        }

        return self::namedTargets(self::parts($operation->getWhen()), $type);
    }

    /**
     * Блоки spec из кодов условия. Пустое условие — все три блока.
     *
     * @param ItemModifierApplies $when Условие.
     *
     * @return array<int, string> weapon, shield или armor.
     */
    public static function parts(ItemModifierApplies $when): array
    {
        $named = [];
        foreach (array_merge($when->getKeywordAll(), $when->getKeywordAny()) as $code) {
            self::remember($named, $code);
        }

        if ($named === []) {
            return ['weapon', 'shield', 'armor'];
        }

        return array_keys($named);
    }

    /**
     * Запрещённый код отсутствует.
     *
     * @param ItemModifierApplies $when Условие.
     * @param array<string, true> $codes Признаки предмета.
     *
     * @return bool true, если ни один запрет не встретился.
     */
    private static function passesNone(ItemModifierApplies $when, array $codes): bool
    {
        foreach ($when->getKeywordNone() as $code) {
            if (isset($codes[$code])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Все обязательные коды есть.
     *
     * @param ItemModifierApplies $when Условие.
     * @param array<string, true> $codes Признаки предмета.
     *
     * @return bool true, если каждый код из keyword_all есть.
     */
    private static function passesAll(ItemModifierApplies $when, array $codes): bool
    {
        foreach ($when->getKeywordAll() as $code) {
            if (!isset($codes[$code])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Хотя бы один код из keyword_any. Пустой список проходит.
     *
     * @param ItemModifierApplies $when Условие.
     * @param array<string, true> $codes Признаки предмета.
     *
     * @return bool true, если список пуст или один код есть.
     */
    private static function passesAny(ItemModifierApplies $when, array $codes): bool
    {
        $any = $when->getKeywordAny();
        if ($any === []) {
            return true;
        }

        foreach ($any as $code) {
            if (isset($codes[$code])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Одно поле предмета, не блок оружия или щита.
     *
     * @param string $type Тип операции.
     *
     * @return array<int, string> Ключ или пусто.
     */
    private static function itemTarget(string $type): array
    {
        if ($type === 'weight') {
            return ['weight'];
        }

        if ($type === 'block') {
            return ['block'];
        }

        return [];
    }

    /**
     * Ключи part.type.
     *
     * @param array<int, string> $parts Блоки.
     * @param string $type Тип операции.
     *
     * @return array<int, string> Ключи.
     */
    private static function namedTargets(array $parts, string $type): array
    {
        $targets = [];
        foreach ($parts as $part) {
            $targets[] = $part . '.' . $type;
        }

        return $targets;
    }

    /**
     * Запоминает блок, если код условия его называет.
     *
     * @param array<string, true> $named Уже выбранные блоки.
     * @param string $code Код из условия.
     *
     * @return void
     */
    private static function remember(array &$named, string $code): void
    {
        if ($code === self::WEAPON) {
            $named['weapon'] = true;
        }

        if ($code === self::SHIELD) {
            $named['shield'] = true;
        }

        if ($code === self::ARMOR) {
            $named['armor'] = true;
        }
    }
}
