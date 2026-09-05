<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

/**
 * Аудитория сообщения: всем членам или список id.
 */
final class MessageAudience
{
    public const ALL = 'all';

    public const USERS = 'users';

    /**
     * Создаёт аудиторию.
     *
     * @param string $kind `all` или `users`.
     * @param array<int, int> $userIds Id при `users`; при `all` пусто.
     *
     * @return void
     */
    private function __construct(
        private readonly string $kind,
        private readonly array $userIds,
    ) {
    }

    /**
     * Всем членам.
     *
     * @return self Аудитория.
     */
    public static function all(): self
    {
        return new self(self::ALL, []);
    }

    /**
     * Список зрителей; автор виден через user_id строки.
     *
     * @param array<int, int> $userIds Уникальные id членов.
     *
     * @return self Аудитория.
     */
    public static function forUsers(array $userIds): self
    {
        return new self(self::USERS, array_values($userIds));
    }

    /**
     * Колонка audience.
     *
     * @return string `all` или `users`.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Id в mfv.
     *
     * @return array<int, int> Список; при `all` пусто.
     */
    public function getUserIds(): array
    {
        return $this->userIds;
    }
}
