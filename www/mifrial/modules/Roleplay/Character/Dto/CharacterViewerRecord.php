<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Грант секций одному зрителю. Не JSON HTTP.
 */
final class CharacterViewerRecord
{
    /**
     * Создаёт грант.
     *
     * @param int $userId Зритель.
     * @param array $fields Секции.
     *
     * @return void
     */
    private function __construct(
        private readonly int $userId,
        private readonly array $fields,
    ) {
    }

    /**
     * Собирает грант из строки или уже разобранного входа.
     *
     * @param array<string, mixed> $fields Колонки user_id и fields.
     *
     * @return self Грант.
     *
     * @throws CharacterInvalidException Если форма неполная.
     */
    public static function fromNormalized(array $fields): self
    {
        $userId = $fields['user_id'] ?? null;
        $sectionFields = $fields['fields'] ?? null;
        if (!is_int($userId) || !is_array($sectionFields)) {
            throw new CharacterInvalidException('Character viewer record is incomplete');
        }

        return new self($userId, $sectionFields);
    }

    /**
     * Учётка зрителя.
     *
     * @return int Id.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Секции этого зрителя.
     *
     * @return array Коды.
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
