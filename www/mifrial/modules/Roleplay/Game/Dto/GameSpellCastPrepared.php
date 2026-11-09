<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Проверенный и рассчитанный вход серверного каста.
 */
final class GameSpellCastPrepared
{
    /**
     * Собирает подготовку.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param AbilitySpellSpec $spell Живая карточка заклинания.
     * @param MagicPathSpec $path Живой путь.
     * @param string $spellCode Код заклинания.
     * @param string $pathCode Код пути.
     * @param string $casterKind Вид кастера.
     * @param int $casterId Идентификатор кастера.
     * @param CharacterChoices $casterModel Выборы кастера.
     * @param array<string, mixed> $casterChoices Документ choices кастера.
     * @param array<string, mixed> $casterSheet Пересчитанный лист кастера.
     * @param int $casterVersion Версия листа кастера.
     * @param array{
     *     type: string,
     *     id: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>,
     *     version: int
     * }|null $target Цель или null.
     * @param array{
     *     type: string,
     *     id: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>,
     *     version: int
     * }|null $damageTarget Цель эффекта: заклинания или касания.
     * @param array<string, int|array{base: int, size: int}> $parameters Активационные параметры.
     * @param CharacteristicNumber $usedPower Используемая мощь.
     * @param DimensionalNumber $requiredPower Требуемая мощь.
     * @param DimensionalNumber $requiredControl Требуемый контроль.
     * @param DimensionalNumber $availableControl Доступный контроль.
     * @param list<ResourceSpend> $spends Траты компонентов.
     * @param int $spent Списанные ОД.
     * @param int $distanceIpari Дистанция в ипари.
     *
     * @return void
     */
    public function __construct(
        public readonly CharacterRuleSlice $slice,
        public readonly AbilitySpellSpec $spell,
        public readonly MagicPathSpec $path,
        public readonly string $spellCode,
        public readonly string $pathCode,
        public readonly string $casterKind,
        public readonly int $casterId,
        public readonly CharacterChoices $casterModel,
        public readonly array $casterChoices,
        public readonly array $casterSheet,
        public readonly int $casterVersion,
        public readonly ?array $target,
        public readonly ?array $damageTarget,
        public readonly array $parameters,
        public readonly CharacteristicNumber $usedPower,
        public readonly DimensionalNumber $requiredPower,
        public readonly DimensionalNumber $requiredControl,
        public readonly DimensionalNumber $availableControl,
        public readonly array $spends,
        public readonly int $spent,
        public readonly int $distanceIpari,
    ) {
    }
}
