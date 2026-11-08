<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto;

use Mifrial\Messages\Notes\Exception\NoteInvalidException;

/**
 * Поля новой заметки.
 */
final class NewNote
{
    /**
     * Создаёт DTO из проверенных полей.
     *
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param string $title Заголовок.
     * @param string $bodyHtml HTML.
     *
     * @return void
     */
    private function __construct(
        private readonly string $hostType,
        private readonly int $hostId,
        private readonly string $title,
        private readonly string $bodyHtml,
    ) {
    }

    /**
     * Проверяет и собирает новую заметку.
     *
     * @param array<string, mixed> $values Поля запроса.
     *
     * @return self Заметка.
     *
     * @throws NoteInvalidException Если поля недопустимы.
     */
    public static function fromNormalized(array $values): self
    {
        $hostType = $values['hostType'] ?? null;
        if (!is_string($hostType) || preg_match('/^[a-z][a-z0-9_]{0,31}$/', $hostType) !== 1) {
            throw new NoteInvalidException('Note host type is invalid');
        }

        $hostId = $values['hostId'] ?? null;
        if (!is_int($hostId) || $hostId < 1) {
            throw new NoteInvalidException('Note host id is invalid');
        }

        $title = $values['title'] ?? null;
        if (!is_string($title)) {
            throw new NoteInvalidException('Note title is invalid');
        }

        $title = trim($title);
        if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
            throw new NoteInvalidException('Note title is invalid');
        }

        $bodyHtml = $values['bodyHtml'] ?? '';
        if (!is_string($bodyHtml) || strlen($bodyHtml) > 65535) {
            throw new NoteInvalidException('Note body is invalid');
        }

        return new self($hostType, $hostId, $title, $bodyHtml);
    }

    /**
     * Тип сущности.
     *
     * @return string Тип.
     */
    public function getHostType(): string
    {
        return $this->hostType;
    }

    /**
     * Id сущности.
     *
     * @return int Id.
     */
    public function getHostId(): int
    {
        return $this->hostId;
    }

    /**
     * Заголовок.
     *
     * @return string Заголовок.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * HTML.
     *
     * @return string HTML.
     */
    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }
}
