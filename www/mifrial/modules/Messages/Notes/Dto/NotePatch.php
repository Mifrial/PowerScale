<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto;

use Mifrial\Messages\Notes\Exception\NoteInvalidException;

/**
 * Частичное обновление заметки: null — «не менять».
 */
final class NotePatch
{
    /**
     * Создаёт DTO из проверенных полей.
     *
     * @param string|null $title Заголовок.
     * @param string|null $bodyHtml HTML.
     * @param bool|null $pinned Закрепление.
     *
     * @return void
     */
    private function __construct(
        private readonly ?string $title,
        private readonly ?string $bodyHtml,
        private readonly ?bool $pinned,
    ) {
    }

    /**
     * Проверяет и собирает patch.
     *
     * @param array<string, mixed> $values Поля запроса.
     *
     * @return self Patch.
     *
     * @throws NoteInvalidException Если поля недопустимы.
     */
    public static function fromNormalized(array $values): self
    {
        $title = $values['title'] ?? null;
        if ($title !== null) {
            if (!is_string($title)) {
                throw new NoteInvalidException('Note title is invalid');
            }

            $title = trim($title);
            if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
                throw new NoteInvalidException('Note title is invalid');
            }
        }

        $bodyHtml = $values['bodyHtml'] ?? null;
        if ($bodyHtml !== null && (!is_string($bodyHtml) || strlen($bodyHtml) > 65535)) {
            throw new NoteInvalidException('Note body is invalid');
        }

        $pinned = $values['pinned'] ?? null;
        if ($pinned !== null && !is_bool($pinned)) {
            throw new NoteInvalidException('Note pinned is invalid');
        }

        return new self($title, $bodyHtml, $pinned);
    }

    /**
     * Заголовок или null.
     *
     * @return string|null Заголовок.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * HTML или null.
     *
     * @return string|null HTML.
     */
    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    /**
     * Закрепление или null.
     *
     * @return bool|null Флаг.
     */
    public function getPinned(): ?bool
    {
        return $this->pinned;
    }

    /**
     * Есть ли что писать.
     *
     * @return bool true, если хотя бы одно поле задано.
     */
    public function isEmpty(): bool
    {
        return $this->title === null && $this->bodyHtml === null && $this->pinned === null;
    }
}
