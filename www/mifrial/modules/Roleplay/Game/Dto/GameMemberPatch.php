<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Смена роли участника.
 */
final class GameMemberPatch
{
    /**
     * Создаёт patch.
     *
     * @param string $role Роль.
     *
     * @return void
     */
    private function __construct(
        private readonly string $role,
    ) {
    }

    /**
     * Оборачивает роль.
     *
     * @param array<string, mixed> $values Ключи домена.
     *
     * @return self Patch.
     *
     * @throws GameInvalidException Если тип неверен.
     */
    public static function fromNormalized(array $values): self
    {
        $role = $values['role'] ?? null;
        if (!is_string($role)) {
            throw new GameInvalidException('Game member field is invalid');
        }

        return new self($role);
    }

    /**
     * Роль.
     *
     * @return string Код.
     */
    public function getRole(): string
    {
        return $this->role;
    }
}
