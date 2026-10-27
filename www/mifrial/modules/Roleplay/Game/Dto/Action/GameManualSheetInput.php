<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.manualSheet.
 */
final class GameManualSheetInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ повтора.
     * @param string $operation Код: setResource, addState, patchState, removeState, setEquipped или setOccupyHands.
     * @param array $target Чей лист.
     * @param int $expectedSheetVersion Версия листа цели.
     * @param array $details Поля операции.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly string $idempotencyKey,
        public readonly string $operation,
        public readonly array $target,
        public readonly int $expectedSheetVersion,
        public readonly array $details,
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
            'target' => $this->target,
            'expectedSheetVersion' => $this->expectedSheetVersion,
            'details' => $this->details,
        ];
    }
}
