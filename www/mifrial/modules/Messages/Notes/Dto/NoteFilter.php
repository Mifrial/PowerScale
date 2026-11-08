<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Геттеры — поля фильтра, не порты DI.

namespace Mifrial\Messages\Notes\Dto;

use Mifrial\Messages\Notes\Exception\NoteInvalidException;

/**
 * Фильтр страницы заметок.
 */
final class NoteFilter
{
    /**
     * Создаёт фильтр из проверенных полей.
     *
     * @param string|null $q Подстрока.
     * @param string|null $hostType Тип сущности.
     * @param int|null $hostId Id сущности.
     * @param int|null $authorId Автор.
     * @param bool|null $pinned Закрепление.
     * @param string $state active/archived/all.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     */
    private function __construct(
        private readonly ?string $q,
        private readonly ?string $hostType,
        private readonly ?int $hostId,
        private readonly ?int $authorId,
        private readonly ?bool $pinned,
        private readonly string $state,
        private readonly int $limit,
        private readonly int $offset,
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

        $hostId = self::optionalPositiveInt($values['hostId'] ?? null, 'Note host id is invalid');
        $authorId = self::optionalPositiveInt($values['authorId'] ?? null, 'Note author id is invalid');

        $pinned = $values['pinned'] ?? null;
        if ($pinned !== null && !is_bool($pinned)) {
            throw new NoteInvalidException('Note pinned is invalid');
        }

        $state = $values['state'] ?? 'active';
        if (!is_string($state) || !in_array($state, ['active', 'archived', 'all'], true)) {
            throw new NoteInvalidException('Note state is invalid');
        }

        $limit = $values['limit'] ?? 50;
        if (!is_int($limit) || $limit < 1 || $limit > 100) {
            throw new NoteInvalidException('Note page limit is invalid');
        }

        $offset = $values['offset'] ?? 0;
        if (!is_int($offset) || $offset < 0) {
            throw new NoteInvalidException('Note page offset is invalid');
        }

        return new self($q, $hostType, $hostId, $authorId, $pinned, $state, $limit, $offset);
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
        return new self(
            $this->q,
            $this->hostType,
            $this->hostId,
            $authorId,
            $this->pinned,
            $this->state,
            $this->limit,
            $this->offset,
        );
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
     * Id сущности.
     *
     * @return int|null Id.
     */
    public function getHostId(): ?int
    {
        return $this->hostId;
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
     * Закрепление.
     *
     * @return bool|null Флаг.
     */
    public function getPinned(): ?bool
    {
        return $this->pinned;
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
     * Размер страницы.
     *
     * @return int Лимит.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Сдвиг.
     *
     * @return int Сдвиг.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Положительный int или null.
     *
     * @param mixed $value Значение.
     * @param string $message Текст ошибки.
     *
     * @return int|null Id.
     *
     * @throws NoteInvalidException Если значение не положительный int.
     */
    private static function optionalPositiveInt(mixed $value, string $message): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value) || $value < 1) {
            throw new NoteInvalidException($message);
        }

        return $value;
    }
}
