<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Прочитанная группа.
 */
final class GroupRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'name',
        'active',
        'bypass',
        'created_at',
        'permissions',
    ];

    /**
     * Создаёт запись из свойств группы.
     *
     * @param array<string, mixed> $fields Свойства.
     *
     * @return void
     */
    private function __construct(
        private readonly array $fields,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств.
     *
     * @param array<string, mixed> $fields Свойства группы.
     *
     * @return self Группа.
     *
     * @throws UserInvalidException Если нет свойства.
     */
    public static function fromNormalized(array $fields): self
    {
        $groupFields = [];
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new UserInvalidException('Group record is incomplete');
            }

            $groupFields[$fieldName] = $fields[$fieldName];
        }

        return new self($groupFields);
    }

    /**
     * Возвращает свойства группы.
     *
     * @return array<string, mixed> Карта свойства => значение.
     */
    public function values(): array
    {
        return $this->fields;
    }
}
