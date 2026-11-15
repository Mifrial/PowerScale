<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Читатель secret-статьи в JSON-виде.
 */
final class LoreReader
{
    /**
     * Собирает читателя.
     *
     * @param int $id Id учётки.
     * @param string $login Логин.
     * @param string $name Имя.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly string $login,
        private readonly string $name,
    ) {
    }

    /**
     * Id учётки.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Логин.
     *
     * @return string Логин.
     */
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
