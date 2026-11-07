<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Снимок вызова — Record: параметры конструктора это поля действия, не порты.

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Снимок вызова сценария действия.
 */
final class GameActionPerformCall
{
    /**
     * Создаёт снимок.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $actionRuleCode Карточка.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $pending Ожидание.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     * @param array<string, int> $chosenAmounts Выбранные суммы.
     * @param Closure|null $projectConflict Проектор конфликта.
     * @param ProcessStep|null $step Шаг процесса.
     * @param bool $paid Действие уже оплачено.
     *
     * @return void
     */
    public function __construct(
        public readonly GameRecord $game,
        public readonly string $actorKind,
        public readonly int $actorId,
        public readonly string $actionRuleCode,
        public readonly int $expectedSheetVersion,
        public readonly array $pending,
        public readonly bool $allowZeroChosen,
        public readonly array $chosenAmounts,
        public readonly ?Closure $projectConflict,
        public readonly ?ProcessStep $step,
        public readonly bool $paid,
    ) {
    }
}
