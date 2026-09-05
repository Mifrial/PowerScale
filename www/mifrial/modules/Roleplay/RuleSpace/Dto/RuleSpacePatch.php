<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto;

use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Частичное обновление мета мира: только переданные свойства.
 */
final class RuleSpacePatch
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = ['name', 'description'];

    /**
     * Создаёт Patch.
     *
     * @param array<string, mixed> $values Присутствующие поля.
     *
     * @return void
     */
    private function __construct(
        private readonly array $values,
    ) {
    }

    /**
     * Проверяет ключи и типы.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return self Patch.
     *
     * @throws RuleSpaceInvalidException Если ключ или тип недопустимы.
     */
    public static function fromNormalized(array $values): self
    {
        foreach ($values as $fieldName => $fieldValue) {
            if (!is_string($fieldName) || !in_array($fieldName, self::FIELD_NAMES, true)) {
                throw new RuleSpaceInvalidException('Rule space patch field is invalid');
            }

            if (!is_string($fieldValue)) {
                throw new RuleSpaceInvalidException('Rule space patch field is invalid');
            }
        }

        return new self($values);
    }

    /**
     * Присутствующие свойства.
     *
     * @return array<string, mixed> Поля.
     */
    public function fields(): array
    {
        return $this->values;
    }
}
