<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Exception;

use Throwable;

/**
 * CAS строки персонажа: actual или membership устарели.
 */
final class GameConflictException extends GameException
{
    /**
     * Создаёт конфликт версий.
     *
     * @param int $currentActualVersion Текущий actual_version.
     * @param int $currentMembershipRevision Текущая revision строки.
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly int $currentActualVersion,
        private readonly int $currentMembershipRevision,
        string $message = 'Game character version conflict',
        ?Throwable $previous = null,
    ) {
        parent::__construct('GAME_CONFLICT', $message, $previous);
    }

    /**
     * Версии в конверте ошибки.
     *
     * @return array<string, int> Два счётчика.
     */
    public function getErrorDetails(): array
    {
        return [
            'currentActualVersion' => $this->currentActualVersion,
            'currentMembershipRevision' => $this->currentMembershipRevision,
        ];
    }
}
