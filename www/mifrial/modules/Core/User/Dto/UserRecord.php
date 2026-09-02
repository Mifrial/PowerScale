<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Прочитанная учётка.
 */
final class UserRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'login',
        'email',
        'name',
        'surname',
        'nickname',
        'active',
        'registered_at',
        'deactivated_until',
        'deactivate_reason',
    ];

    /**
     * Создаёт запись из свойств профиля.
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
     * @param array<string, mixed> $fields Свойства учётки.
     *
     * @return self Учётка.
     *
     * @throws UserInvalidException Если нет свойства.
     */
    public static function fromNormalized(array $fields): self
    {
        $profileFields = [];
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new UserInvalidException('User record is incomplete');
            }

            $profileFields[$fieldName] = $fields[$fieldName];
        }

        return new self($profileFields);
    }

    /**
     * Возвращает свойства учётки.
     *
     * @return array<string, mixed> Карта свойства => значение.
     */
    public function values(): array
    {
        return $this->fields;
    }
}
