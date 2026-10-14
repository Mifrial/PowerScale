<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.woundAction.
 */
final class GameWoundInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ повтора.
     * @param string $operation hold, release или bandage.
     * @param array $actor Кто действует.
     * @param array $target Чей лист.
     * @param int $stateId Строка состояния.
     * @param string $actionRuleCode Карточка действия.
     * @param int $expectedSheetVersion Версия листа цели.
     * @param int $battleId Бой или 0.
     * @param int $expectedBattleVersion Версия боя или 0.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $idempotencyKey,
        public readonly string $operation,
        public readonly array $actor,
        public readonly array $target,
        public readonly int $stateId,
        public readonly string $actionRuleCode,
        public readonly int $expectedSheetVersion,
        public readonly int $battleId,
        public readonly int $expectedBattleVersion,
    ) {
    }

    /**
     * Тело replay.
     *
     * @return array<string, mixed> Поля команды.
     */
    public function body(): array
    {
        return [
            'idempotencyKey' => $this->idempotencyKey,
            'operation' => $this->operation,
            'actor' => $this->actor,
            'target' => $this->target,
            'stateId' => $this->stateId,
            'actionRuleCode' => $this->actionRuleCode,
            'expectedSheetVersion' => $this->expectedSheetVersion,
            'battleId' => $this->battleId,
            'expectedBattleVersion' => $this->expectedBattleVersion,
        ];
    }
}
