<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellValue;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Потолок мощи поддержания: форма длительности, мощь источника и штраф отклонения.
 */
final class GameSpellPowerCap
{
    /**
     * Создаёт расчёт.
     *
     * @param GameSpellSourcePower $sourcePower Мощь выбранного источника.
     *
     * @return void
     */
    public function __construct(private readonly GameSpellSourcePower $sourcePower)
    {
    }

    /**
     * Пределы мощи из формы длительности: минимум и максимум параметра.
     *
     * @param AbilitySpellSpec $card Живая карточка.
     *
     * @return array{min: DimensionalNumber|null, max: DimensionalNumber|null} Пределы.
     */
    public function limits(AbilitySpellSpec $card): array
    {
        $power = $card->getSpell()->getDuration()->getPower();
        if (!$power instanceof SpellValue) {
            return ['min' => null, 'max' => null];
        }

        $code = $power->getParameterCode();
        if ($code === null) {
            return ['min' => null, 'max' => null];
        }

        foreach ($card->getBase()->getParameters() as $parameter) {
            if ($parameter->getCode() !== $code) {
                continue;
            }

            return [
                'min' => $this->number($parameter->getMin()),
                'max' => $this->number($parameter->getMax()),
            ];
        }

        return ['min' => null, 'max' => null];
    }

    /**
     * Предел параметра в размерное число.
     *
     * @param int|DimensionalNumber|null $value Значение.
     *
     * @return DimensionalNumber|null Число или null.
     */
    private function number(int|DimensionalNumber|null $value): ?DimensionalNumber
    {
        if ($value instanceof DimensionalNumber) {
            return $value;
        }

        return is_int($value) ? new DimensionalNumber($value, 0) : null;
    }

    /**
     * Потолок мощи источника поддержания.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param MagicPathSpec $path Путь каста.
     * @param array<string, mixed> $sheet Пересчитанный лист.
     * @param string $sourceKey Ключ источника.
     * @param array<string, mixed> $choices Документ choices кастера.
     * @param CharacterChoices $model Выборы кастера.
     *
     * @return CharacteristicNumber|null Потолок или null, если у источника нет мощи.
     */
    public function cap(
        CharacterRuleSlice $slice,
        MagicPathSpec $path,
        array $sheet,
        string $sourceKey,
        array $choices,
        CharacterChoices $model,
    ): ?CharacteristicNumber {
        $source = $this->source($sourceKey);
        if ($source === null) {
            return null;
        }

        return $this->sourcePower->resolve($slice, $path, $source, $choices, $sheet, $model);
    }

    /**
     * Ключ источника в нормализованный вид.
     *
     * @param string $sourceKey Ключ вида inventory:9, grant:x или characteristic:y.
     *
     * @return array<string, mixed>|null Источник или null.
     */
    private function source(string $sourceKey): ?array
    {
        $parts = explode(':', $sourceKey, 2);
        if (count($parts) !== 2 || $parts[1] === '') {
            return null;
        }

        return match ($parts[0]) {
            'inventory' => ['kind' => 'item', 'inventoryId' => (int) $parts[1]],
            'grant' => ['kind' => 'grant', 'abilityCode' => $parts[1]],
            'characteristic' => ['kind' => 'characteristic', 'code' => $parts[1]],
            default => null,
        };
    }
}
