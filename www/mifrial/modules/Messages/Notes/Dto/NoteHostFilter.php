<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto;

use Mifrial\Messages\Notes\Exception\NoteInvalidException;

/**
 * Фильтр левой колонки `/notes`: сущности, у которых есть заметки.
 */
final class NoteHostFilter
{
    /**
     * Создаёт фильтр из проверенных полей.
     *
     * @param string|null $q Подстрока.
     * @param string|null $hostType Тип сущности.
     * @param int|null $authorId Автор.
     * @param string $state active/archived/all.
     * @param int $limit Число групп.
     *
     * @return void
     */
    private function __construct(
        private readonly ?string $q,
        private readonly ?string $hostType,
        private readonly ?int $authorId,
        private readonly string $state,
        private readonly int $limit,
    ) {
    }

    /**
     * Проверяет и собирает фильтр.
     *
     * @param array<string, mixed> $values Поля запроса.
     *
     * @return self Фильтр.
     *
     * @throws NoteInvalidException Если поля недопустимы.
     */
    public static function fromNormalized(array $values): self
    {
        $q = $values['q'] ?? null;
        if ($q !== null) {
            if (!is_string($q)) {
                throw new NoteInvalidException('Note search query is invalid');
            }

            $q = trim($q);
            if ($q === '') {
                $q = null;
            } elseif (mb_strlen($q, 'UTF-8') > 100) {
                throw new NoteInvalidException('Note search query is invalid');
            }
        }

        $hostType = $values['hostType'] ?? null;
        if ($hostType !== null && (!is_string($hostType) || preg_match('/^[a-z][a-z0-9_]{0,31}$/', $hostType) !== 1)) {
            throw new NoteInvalidException('Note host type is invalid');
        }

        $authorId = $values['authorId'] ?? null;
        if ($authorId !== null && (!is_int($authorId) || $authorId < 1)) {
            throw new NoteInvalidException('Note author id is invalid');
        }

        $state = $values['state'] ?? 'active';
        if (!is_string($state) || !in_array($state, ['active', 'archived', 'all'], true)) {
            throw new NoteInvalidException('Note state is invalid');
        }

        $limit = $values['limit'] ?? 200;
        if (!is_int($limit) || $limit < 1 || $limit > 500) {
            throw new NoteInvalidException('Note host limit is invalid');
        }

        return new self($q, $hostType, $authorId, $state, $limit);
    }

    /**
     * Копия с принудительным автором.
     *
     * @param int $authorId Автор.
     *
     * @return self Фильтр.
     */
    public function withAuthorId(int $authorId): self
    {
        return new self($this->q, $this->hostType, $authorId, $this->state, $this->limit);
    }

    /**
     * Подстрока.
     *
     * @return string|null q.
     */
    public function getQ(): ?string
    {
        return $this->q;
    }

    /**
     * Тип сущности.
     *
     * @return string|null Тип.
     */
    public function getHostType(): ?string
    {
        return $this->hostType;
    }

    /**
     * Автор.
     *
     * @return int|null Id.
     */
    public function getAuthorId(): ?int
    {
        return $this->authorId;
    }

    /**
     * Состояние.
     *
     * @return string active/archived/all.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Число групп.
     *
     * @return int Лимит.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
