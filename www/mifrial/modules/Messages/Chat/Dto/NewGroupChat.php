<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;

/**
 * Поля нового группового чата без дат.
 */
final class NewGroupChat
{
    /**
     * Создаёт DTO из уже проверенных полей.
     *
     * @param string $name Имя после trim.
     * @param int $creatorId Создатель.
     * @param array<int, int> $memberIds Дополнительные члены.
     *
     * @return void
     */
    private function __construct(
        private readonly string $name,
        private readonly int $creatorId,
        private readonly array $memberIds,
    ) {
    }

    /**
     * Оборачивает нормализованные свойства группы.
     *
     * @param array<string, mixed> $values Уже проверенный набор.
     *
     * @return self Новая группа.
     *
     * @throws ChatInvalidException Если ключи или типы неверны.
     */
    public static function fromNormalized(array $values): self
    {
        if (!is_string($values['name'] ?? null) || !is_int($values['creatorId'] ?? null)) {
            throw new ChatInvalidException('Group chat values are invalid');
        }

        return new self($values['name'], $values['creatorId'], self::memberIds($values['memberIds'] ?? []));
    }

    /**
     * Имя группы.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Создатель.
     *
     * @return int Id учётки.
     */
    public function getCreatorId(): int
    {
        return $this->creatorId;
    }

    /**
     * Дополнительные члены без создателя, если его не передали.
     *
     * @return array<int, int> Id учёток.
     */
    public function getMemberIds(): array
    {
        return $this->memberIds;
    }

    /**
     * Список id членов.
     *
     * @param mixed $memberIds Вход.
     *
     * @return array<int, int> Id.
     *
     * @throws ChatInvalidException Если не список int.
     */
    private static function memberIds(mixed $memberIds): array
    {
        if (!is_array($memberIds)) {
            throw new ChatInvalidException('Group chat values are invalid');
        }

        $ids = [];
        foreach ($memberIds as $memberId) {
            if (!is_int($memberId)) {
                throw new ChatInvalidException('Group chat values are invalid');
            }

            $ids[] = $memberId;
        }

        return $ids;
    }
}
