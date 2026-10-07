<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.answerCheck.
 */
final class AnswerGameCheckInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $answer Решение.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $processId,
        public readonly string $idempotencyKey,
        public readonly int $expectedSheetVersion,
        public readonly array $answer,
    ) {
    }
}
