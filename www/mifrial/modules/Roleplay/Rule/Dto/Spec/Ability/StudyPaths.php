<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Пути, на которых карточка изучается или грант открывает изучение.
 *
 * `any` означает любой путь ревизии; список — только перечисленные пути.
 */
final class StudyPaths
{
    public const ANY = 'any';

    /**
     * Создаёт набор.
     *
     * @param bool $any Любой путь.
     * @param array<int, string> $codes Перечисленные пути.
     *
     * @return void
     */
    private function __construct(
        private readonly bool $any,
        private readonly array $codes,
    ) {
    }

    /**
     * Набор из значения ключа. Ключа нет — null.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return self|null Набор или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function optional(array $document, string $key): ?self
    {
        if (!array_key_exists($key, $document) || $document[$key] === null) {
            return null;
        }

        $value = $document[$key];
        if ($value === self::ANY) {
            return new self(true, []);
        }

        if (!is_array($value) || $value === []) {
            throw new RuleSpecShapeException($key);
        }

        return new self(false, self::codes($value, $key));
    }

    /**
     * Пустой набор: ничего не открывает. Нужен для прежних ревизий.
     *
     * @return self Набор.
     */
    public static function none(): self
    {
        return new self(false, []);
    }

    /**
     * Набор из списка кодов.
     *
     * @param array<mixed> $values Список.
     * @param string $key Ключ для отказа.
     *
     * @return self Набор.
     *
     * @throws RuleSpecShapeException Если элемент не строка.
     */
    public static function fromList(array $values, string $key): self
    {
        if ($values === []) {
            throw new RuleSpecShapeException($key);
        }

        return new self(false, self::codes($values, $key));
    }

    /**
     * Любой путь ревизии.
     *
     * @return bool true, если набор не ограничен.
     */
    public function isAny(): bool
    {
        return $this->any;
    }

    /**
     * Перечисленные пути. Для `any` список пуст.
     *
     * @return array<int, string> Коды.
     */
    public function getCodes(): array
    {
        return $this->codes;
    }

    /**
     * Путь входит в набор без учёта включений.
     *
     * @param string $code Код пути.
     *
     * @return bool true, если входит.
     */
    public function allows(string $code): bool
    {
        return $this->any || in_array($code, $this->codes, true);
    }

    /**
     * Проверяет список кодов.
     *
     * @param array<mixed> $values Список.
     * @param string $key Ключ для отказа.
     *
     * @return array<int, string> Уникальные коды.
     *
     * @throws RuleSpecShapeException Если элемент не строка или пуст.
     */
    private static function codes(array $values, string $key): array
    {
        $codes = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new RuleSpecShapeException($key);
            }

            if (!in_array($value, $codes, true)) {
                $codes[] = $value;
            }
        }

        return $codes;
    }
}
