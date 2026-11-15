<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля подготовки — публичный снимок, не порты DI.

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;

/**
 * Проверенный снимок поддерживаемого заклинания для команды хода.
 */
final class GameActiveSpellPrepared
{
    /**
     * Собирает снимок.
     *
     * @param GameActiveSpellRecord $record Строка.
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param AbilitySpellSpec $card Живая карточка.
     * @param MagicPathSpec $path Живой путь.
     * @param array<string, mixed> $choices Документ choices кастера.
     * @param CharacterChoices $model Выборы кастера.
     * @param array<string, mixed> $sheet Пересчитанный лист кастера.
     * @param int $sheetVersion Версия листа кастера.
     * @param array{base: int, size: int}|null $defaultSustainPower Мощь из формы карточки.
     * @param bool $sourceAvailable Источник ещё доступен.
     *
     * @return void
     */
    public function __construct(
        public readonly GameActiveSpellRecord $record,
        public readonly CharacterRuleSlice $slice,
        public readonly AbilitySpellSpec $card,
        public readonly MagicPathSpec $path,
        public readonly array $choices,
        public readonly CharacterChoices $model,
        public readonly array $sheet,
        public readonly int $sheetVersion,
        public readonly ?array $defaultSustainPower,
        public readonly bool $sourceAvailable,
    ) {
    }
}
