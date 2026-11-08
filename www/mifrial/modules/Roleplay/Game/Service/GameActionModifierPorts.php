<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Собранные порты исполнителя модификаторов: параметры конструктора — порты, не поля строки.

namespace Mifrial\Roleplay\Game\Service;

/**
 * Порты исполнения модификаторов действия.
 */
final class GameActionModifierPorts
{
    /**
     * Создаёт порты.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Последствия урона.
     * @param GameStrikeConsequences $consequences Бросок и чат.
     * @param GameStrikeChat $chat Подписи участников.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeResolutionContext $context Контекст строки атакующего.
     *
     * @return void
     */
    public function __construct(
        public readonly GameStrikeRules $rules,
        public readonly GameStrikeSheets $sheets,
        public readonly GameStrikeSheetWrites $sheetWrites,
        public readonly GameStrikeResolution $resolution,
        public readonly GameStrikeConsequences $consequences,
        public readonly GameStrikeChat $chat,
        public readonly GameStrikeAmounts $amounts,
        public readonly GameStrikeResolutionContext $context,
    ) {
    }
}
