<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Value;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Mifrial\Core\Kernel\Exception\KernelException;
use stdClass;
use Traversable;

/**
 * JSON-объект: не список, ключи в том числе «0».
 *
 * @implements ArrayAccess<string|int, mixed>
 * @implements IteratorAggregate<string|int, mixed>
 */
final class JsonObject implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    /**
     * Создаёт словарь.
     *
     * @param array<string|int, mixed> $members Поля.
     *
     * @return void
     */
    public function __construct(
        private readonly array $members,
    ) {
    }

    /**
     * Поля как PHP-массив. Вложенные объекты остаются JsonObject.
     *
     * @return array<string|int, mixed> Поля.
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * Вложенные JSON-объекты как PHP-массивы: контракт array-параметров HTTP.
     *
     * @return array<string|int, mixed> Поля.
     */
    public function toNestedArray(): array
    {
        return self::unwrapValue($this->members);
    }

    /**
     * JsonObject в массиве и в детях — в PHP-массив.
     *
     * @param mixed $value Узел.
     *
     * @return mixed PHP-значение.
     */
    public static function unwrapValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return self::unwrapValue($value->getMembers());
        }

        if (!is_array($value)) {
            return $value;
        }

        $unwrapped = [];
        foreach ($value as $key => $child) {
            $unwrapped[$key] = self::unwrapValue($child);
        }

        return $unwrapped;
    }

    /**
     * {@inheritdoc}
     *
     * @param string|int $offset Ключ.
     *
     * @return bool true, если ключ есть.
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->members);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|int $offset Ключ.
     *
     * @return mixed Значение или null.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->members[$offset] ?? null;
    }

    /**
     * Словарь неизменяемый.
     *
     * @param string|int $offset Ключ.
     * @param mixed $value Значение.
     *
     * @return void
     *
     * @throws KernelException Всегда.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new KernelException('KERNEL_INVALID', 'JsonObject is immutable');
    }

    /**
     * Словарь неизменяемый.
     *
     * @param string|int $offset Ключ.
     *
     * @return void
     *
     * @throws KernelException Всегда.
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new KernelException('KERNEL_INVALID', 'JsonObject is immutable');
    }

    /**
     * {@inheritdoc}
     *
     * @return Traversable<string|int, mixed> Поля.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->members);
    }

    /**
     * Кодирует как JSON-объект, не как массив.
     *
     * @return stdClass Объект.
     */
    public function jsonSerialize(): stdClass
    {
        $object = new stdClass();
        foreach ($this->members as $key => $value) {
            $object->{(string) $key} = $value;
        }

        return $object;
    }
}
