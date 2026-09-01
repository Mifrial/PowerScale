<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Настройки поля: флаги, default, подпись.
 */
final class FieldSettings
{
    /**
     * Создаёт настройки из опций.
     *
     * @param array<string, mixed> $values Нормализованные опции.
     *
     * @return void
     */
    private function __construct(
        private readonly array $values,
    ) {
    }

    /**
     * Собирает настройки из массива опций.
     *
     * @param array<string, mixed> $options Опции поля.
     *
     * @return self Настройки.
     */
    public static function fromOptions(array $options = []): self
    {
        return new self([
            'label' => is_string($options['label'] ?? null) ? $options['label'] : '',
            'required' => ($options['required'] ?? false) === true,
            'multiple' => ($options['multiple'] ?? false) === true,
            'hasDefault' => array_key_exists('default', $options),
            'default' => $options['default'] ?? null,
            'indexed' => ($options['indexed'] ?? false) === true,
            'unique' => ($options['unique'] ?? false) === true,
        ]);
    }

    /**
     * Возвращает ключ подписи или пустую строку.
     *
     * @return string Ключ сообщения.
     */
    public function label(): string
    {
        return $this->values['label'];
    }

    /**
     * Возвращает обязательность значения в хранимой строке.
     *
     * @return bool true, если null запрещён.
     */
    public function required(): bool
    {
        return $this->values['required'];
    }

    /**
     * Возвращает флаг multiple.
     *
     * @return bool true, если поле списковое.
     */
    public function multiple(): bool
    {
        return $this->values['multiple'];
    }

    /**
     * Проверяет, задан ли default.
     *
     * @return bool true, если ключ default был в опциях.
     */
    public function hasDefault(): bool
    {
        return $this->values['hasDefault'];
    }

    /**
     * Возвращает значение по умолчанию.
     *
     * @return mixed Default или null.
     */
    public function defaultValue(): mixed
    {
        return $this->values['default'];
    }

    /**
     * Возвращает флаг индекса.
     *
     * @return bool true, если нужен индекс.
     */
    public function indexed(): bool
    {
        return $this->values['indexed'];
    }

    /**
     * Возвращает флаг unique.
     *
     * @return bool true, если значение уникально.
     */
    public function unique(): bool
    {
        return $this->values['unique'];
    }
}
