<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterSpellSourceBonus;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;

/**
 * Бонусы к потолку мощи источника из формы `spell_source_power`.
 *
 * Расчёт живёт в Character: тот же вклад способностей уже сложен в лист.
 * Обёртка передаёт условие пути и оставляет Game прежний контракт.
 */
final class GameSpellSourceBonus
{
    private readonly CharacterSpellSourceBonus $bonus;

    /**
     * Создаёт расчёт бонусов.
     *
     * @param FormulaEvaluations $evaluations Вычислитель формул.
     *
     * @return void
     */
    public function __construct(FormulaEvaluations $evaluations)
    {
        $this->bonus = new CharacterSpellSourceBonus($evaluations);
    }

    /**
     * Сумма бонусов подходящих способностей кастера.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $model Выборы кастера.
     * @param array<string, mixed> $sheet Пересчитанный лист.
     * @param MagicPathSpec $path Путь каста.
     * @param array<string, mixed> $source Нормализованный источник.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return int Бонус в пунктах шкалы характеристики.
     */
    public function bonus(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $sheet,
        MagicPathSpec $path,
        array $source,
        array $choices,
    ): int {
        return $this->bonus->bonus(
            $slice,
            $model,
            $sheet,
            $path->getCastCheckCode() !== null,
            $source,
            $choices,
        );
    }
}
