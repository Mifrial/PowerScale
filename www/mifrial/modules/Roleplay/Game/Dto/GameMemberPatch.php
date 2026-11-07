<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Смена роли участника.
 */
final class GameMemberPatch
{
    private const INVALID = 'Game member field is invalid';

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
        return new self(GameRowFields::string($values, 'role', self::INVALID));
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
