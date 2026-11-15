<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicParameterGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Потолок мощи выбранного источника каста.
 */
final class GameSpellSourcePower
{
    /**
     * Создаёт расчёт.
     *
     * @param GameSpellDeviation $deviation Штраф отклонения.
     * @param GameSpellSourceBonus $bonus Бонусы формы `spell_source_power`.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSpellDeviation $deviation,
        private readonly GameSpellSourceBonus $bonus,
    ) {
    }

    /**
     * Потолок мощи источника: его мощь, бонус формы и штраф отклонения.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param MagicPathSpec $path Путь каста.
     * @param array<string, mixed> $source Нормализованный источник.
     * @param array<string, mixed> $choices Документ choices кастера.
     * @param array<string, mixed> $sheet Пересчитанный лист.
     * @param CharacterChoices $model Выборы кастера.
     *
     * @return CharacteristicNumber|null Потолок или null, если у источника нет мощи.
     */
    public function resolve(
        CharacterRuleSlice $slice,
        MagicPathSpec $path,
        array $source,
        array $choices,
        array $sheet,
        CharacterChoices $model,
    ): ?CharacteristicNumber {
        $base = $this->base($slice, $path, $source, $choices, $sheet);
        if ($base === null) {
            return null;
        }

        $cap = $base['value'];
        if (!$base['includesBonus']) {
            $bonus = $this->bonus->bonus($slice, $model, $sheet, $path, $source, $choices);
            if ($bonus !== 0) {
                try {
                    $cap = $cap->modify($bonus);
                } catch (RuleInvalidException) {
                    return $cap;
                }
            }
        }

        $key = GameSpellSourceKey::of($source);
        $strength = $this->deviation->strengthOnSource(
            $slice,
            (string) $path->getCastCheckCode(),
            $sheet,
            $key,
        );

        return $this->deviation->penalizedCap($cap, $strength);
    }

    /**
     * Мощь источника и признак «бонусы уже в листе».
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param MagicPathSpec $path Путь.
     * @param array<string, mixed> $source Источник.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{value: CharacteristicNumber, includesBonus: bool}|null
     *         Мощь или null.
     */
    private function base(
        CharacterRuleSlice $slice,
        MagicPathSpec $path,
        array $source,
        array $choices,
        array $sheet,
    ): ?array {
        $fromSheet = $this->sheetSourcePower($source, $path, $sheet);
        if ($fromSheet instanceof CharacteristicNumber) {
            return ['value' => $fromSheet, 'includesBonus' => true];
        }

        return match ($source['kind'] ?? null) {
            'item' => ($value = $this->storagePower($source, $sheet)
                ?? $this->itemPower($slice, $path, $source, $choices)) === null
                ? null
                : ['value' => $value, 'includesBonus' => false],
            'grant', 'characteristic' => ($value = $this->sheetPower($path, $sheet)) === null
                ? null
                : ['value' => $value, 'includesBonus' => is_array($sheet['sourcePowers'] ?? null)],
            default => null,
        };
    }

    /**
     * Предельная мощь кристалла из `sheet.items`.
     *
     * @param array<string, mixed> $source Источник.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return CharacteristicNumber|null Мощь или null.
     */
    private function storagePower(array $source, array $sheet): ?CharacteristicNumber
    {
        $key = GameSpellSourceKey::of($source);
        $row = is_array($sheet['items'] ?? null) ? ($sheet['items'][$key] ?? null) : null;
        $value = is_array($row) ? ($row['maxPower'] ?? null) : null;
        $base = is_array($value) ? ($value['base'] ?? null) : null;
        $size = is_array($value) ? ($value['size'] ?? null) : null;
        if (!is_int($base) || !is_int($size)) {
            return null;
        }

        try {
            return new CharacteristicNumber($base, $size);
        } catch (RuleInvalidException) {
            return null;
        }
    }

    /**
     * Эффективная мощь источника из `sheet.sourcePowers`.
     *
     * @param array<string, mixed> $source Источник.
     * @param MagicPathSpec $path Путь.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return CharacteristicNumber|null Мощь или null.
     */
    private function sheetSourcePower(array $source, MagicPathSpec $path, array $sheet): ?CharacteristicNumber
    {
        if (($source['kind'] ?? null) !== 'item') {
            return null;
        }

        $inventoryId = (int) ($source['inventoryId'] ?? 0);
        $code = $path->getPowerCharacteristicCode();
        foreach (is_array($sheet['sourcePowers'] ?? null) ? $sheet['sourcePowers'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowId = $row['inventoryId'] ?? null;
            $rowCode = $row['characteristicCode'] ?? null;
            $value = $row['value'] ?? null;
            if ($rowId !== $inventoryId || $rowCode !== $code || !is_array($value)) {
                continue;
            }

            $base = $value['base'] ?? null;
            $size = $value['size'] ?? null;
            if (!is_int($base) || !is_int($size)) {
                return null;
            }

            try {
                return new CharacteristicNumber($base, $size);
            } catch (RuleInvalidException) {
                return null;
            }
        }

        return null;
    }

    /**
     * Мощь предмета-источника из параметра экземпляра.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param MagicPathSpec $path Путь.
     * @param array<string, mixed> $source Источник.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return CharacteristicNumber|null Мощь или null.
     */
    private function itemPower(
        CharacterRuleSlice $slice,
        MagicPathSpec $path,
        array $source,
        array $choices,
    ): ?CharacteristicNumber {
        $row = null;
        $inventoryId = (int) ($source['inventoryId'] ?? 0);
        foreach (is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [] as $item) {
            if (is_array($item) && ($item['id'] ?? null) === $inventoryId) {
                $row = $item;
                break;
            }
        }

        $ruleCode = is_array($row) ? ($row['ruleCode'] ?? null) : null;
        if (!is_string($ruleCode) || $ruleCode === '') {
            return null;
        }

        $rule = $slice->findLive($ruleCode);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof ItemSpec) {
            return null;
        }

        $code = $path->getPowerCharacteristicCode();
        foreach ($spec->getGrants() as $grant) {
            if (!$grant instanceof CharacteristicParameterGrant || $grant->getCharacteristicCode() !== $code) {
                continue;
            }

            $parameters = is_array($row['parameters'] ?? null) ? $row['parameters'] : [];
            $value = $parameters[$grant->getParameterCode()] ?? null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            $size = is_array($value) ? ($value['size'] ?? null) : null;
            if (!is_int($base) || !is_int($size)) {
                return null;
            }

            try {
                return new CharacteristicNumber($base * $grant->getPerUnit(), $size);
            } catch (RuleInvalidException) {
                return null;
            }
        }

        return null;
    }

    /**
     * Мощь из характеристики листа: legacy для grant/characteristic.
     *
     * @param MagicPathSpec $path Путь.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return CharacteristicNumber|null Мощь или null.
     */
    private function sheetPower(MagicPathSpec $path, array $sheet): ?CharacteristicNumber
    {
        $code = $path->getPowerCharacteristicCode();
        foreach (is_array($sheet['characteristics'] ?? null) ? $sheet['characteristics'] : [] as $row) {
            if (!is_array($row) || ($row['characteristicCode'] ?? null) !== $code) {
                continue;
            }

            $value = $row['value'] ?? null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            $size = is_array($value) ? ($value['size'] ?? null) : null;
            if (!is_int($base) || !is_int($size)) {
                return null;
            }

            try {
                return new CharacteristicNumber($base, $size);
            } catch (RuleInvalidException) {
                return null;
            }
        }

        return null;
    }
}
