<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.declareAction.
 */
final class DeclareGameActionInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа актора.
     * @param array $actor Актор.
     * @param string $actionRuleCode Карточка действия.
     * @param string|null $followUpTargetKey Цель прошлого удара.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $battleId,
        public readonly string $idempotencyKey,
        public readonly int $expectedVersion,
        public readonly int $expectedSheetVersion,
        public readonly array $actor,
        public readonly string $actionRuleCode,
        public readonly ?string $followUpTargetKey,
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
            'battleId' => $this->battleId,
            'idempotencyKey' => $this->idempotencyKey,
            'expectedVersion' => $this->expectedVersion,
            'expectedSheetVersion' => $this->expectedSheetVersion,
            'actor' => $this->actor,
            'actionRuleCode' => $this->actionRuleCode,
            'followUpTargetKey' => $this->followUpTargetKey,
        ];
    }
}
