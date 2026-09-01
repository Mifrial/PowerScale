<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use BackedEnum;
use Mifrial\Core\Kernel\Dto\BoundArgument;
use ReflectionNamedType;

/**
 * Проверка типов параметров handle.
 */
final class ActionParameterTypeMatcher
{
    /**
     * Сопоставляет значение JSON с именованным типом параметра.
     *
     * @param ReflectionNamedType $parameterType Объявленный тип.
     * @param mixed $value Значение из JSON.
     * @param string $parameterName Имя параметра для сообщения об ошибке.
     *
     * @return BoundArgument Приведённое значение или ошибка.
     */
    public function match(ReflectionNamedType $parameterType, mixed $value, string $parameterName): BoundArgument
    {
        if ($parameterType->isBuiltin()) {
            return $this->matchBuiltin($parameterType->getName(), $value, $parameterName);
        }

        return $this->matchBackedEnum($parameterType->getName(), $value, $parameterName);
    }

    /**
     * Сопоставляет встроенный PHP-тип.
     *
     * @param string $typeName Имя встроенного типа.
     * @param mixed $value Значение из JSON.
     * @param string $parameterName Имя параметра.
     *
     * @return BoundArgument Приведённое значение или ошибка.
     */
    private function matchBuiltin(string $typeName, mixed $value, string $parameterName): BoundArgument
    {
        return match ($typeName) {
            'int' => $this->matchInt($value, $parameterName),
            'float' => $this->matchFloat($value, $parameterName),
            'string' => $this->matchExact(is_string($value), $value, $parameterName),
            'bool' => $this->matchExact(is_bool($value), $value, $parameterName),
            'array' => $this->matchExact(is_array($value), $value, $parameterName),
            'mixed' => BoundArgument::valid($value),
            default => BoundArgument::invalid('Unsupported parameter: ' . $parameterName),
        };
    }

    /**
     * Принимает целое JSON-число, включая 1.0 без дробной части.
     *
     * @param mixed $value Значение из JSON.
     * @param string $parameterName Имя параметра.
     *
     * @return BoundArgument Целое или ошибка.
     */
    private function matchInt(mixed $value, string $parameterName): BoundArgument
    {
        if (is_int($value)) {
            return BoundArgument::valid($value);
        }

        if ($this->isWholeJsonNumber($value)) {
            return BoundArgument::valid((int) $value);
        }

        return BoundArgument::invalid('Invalid parameter: ' . $parameterName);
    }

    /**
     * Принимает JSON-число как float.
     *
     * @param mixed $value Значение из JSON.
     * @param string $parameterName Имя параметра.
     *
     * @return BoundArgument Число или ошибка.
     */
    private function matchFloat(mixed $value, string $parameterName): BoundArgument
    {
        if (is_int($value) || is_float($value)) {
            return BoundArgument::valid((float) $value);
        }

        return BoundArgument::invalid('Invalid parameter: ' . $parameterName);
    }

    /**
     * Проверяет точное совпадение типа.
     *
     * @param bool $isMatch Результат проверки типа.
     * @param mixed $value Значение из JSON.
     * @param string $parameterName Имя параметра.
     *
     * @return BoundArgument Значение или ошибка.
     */
    private function matchExact(bool $isMatch, mixed $value, string $parameterName): BoundArgument
    {
        if ($isMatch) {
            return BoundArgument::valid($value);
        }

        return BoundArgument::invalid('Invalid parameter: ' . $parameterName);
    }

    /**
     * Сопоставляет backed enum по значению.
     *
     * @param string $typeName Имя класса enum.
     * @param mixed $value Значение из JSON.
     * @param string $parameterName Имя параметра.
     *
     * @return BoundArgument Случай enum или ошибка.
     */
    private function matchBackedEnum(string $typeName, mixed $value, string $parameterName): BoundArgument
    {
        if (!is_a($typeName, BackedEnum::class, true)) {
            return BoundArgument::invalid('Unsupported parameter: ' . $parameterName);
        }

        if (!is_string($value) && !is_int($value)) {
            return BoundArgument::invalid('Invalid parameter: ' . $parameterName);
        }

        return $this->enumFromValue($typeName, $value, $parameterName);
    }

    /**
     * Собирает backed enum из скалярного значения.
     *
     * @param class-string<BackedEnum> $typeName Имя enum.
     * @param string|int $value Значение из JSON.
     * @param string $parameterName Имя параметра.
     *
     * @return BoundArgument Случай enum или ошибка.
     */
    private function enumFromValue(string $typeName, string|int $value, string $parameterName): BoundArgument
    {
        $enumCase = $typeName::tryFrom($value);
        if ($enumCase === null) {
            return BoundArgument::invalid('Invalid parameter: ' . $parameterName);
        }

        return BoundArgument::valid($enumCase);
    }

    /**
     * Проверяет, что float из JSON является целым числом в диапазоне int.
     *
     * @param mixed $value Значение из JSON.
     *
     * @return bool true, если значение можно безопасно привести к int.
     */
    private function isWholeJsonNumber(mixed $value): bool
    {
        if (!is_float($value) || !is_finite($value)) {
            return false;
        }

        if ($value > (float) PHP_INT_MAX || $value < (float) PHP_INT_MIN) {
            return false;
        }

        return (float) (int) $value === $value;
    }
}
