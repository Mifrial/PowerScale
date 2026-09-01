<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Dto\BoundArgument;
use Mifrial\Core\Kernel\Dto\ParameterBindResult;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Биндинг JSON на параметры handle.
 */
final class ActionParameterBinder
{
    /**
     * Создаёт биндер параметров действия.
     *
     * @param ActionParameterTypeMatcher $typeMatcher Сопоставление JSON-типов.
     *
     * @return void
     */
    public function __construct(
        private readonly ActionParameterTypeMatcher $typeMatcher = new ActionParameterTypeMatcher(),
    ) {
    }

    /**
     * Собирает аргументы handle из JSON-объекта.
     *
     * @param ReflectionMethod $handleMethod Метод handle обработчика.
     * @param mixed $payload Тело запроса.
     *
     * @return ParameterBindResult Аргументы или INVALID_PARAMS.
     */
    public function bind(ReflectionMethod $handleMethod, mixed $payload): ParameterBindResult
    {
        if (!$this->isObjectPayload($payload)) {
            return ParameterBindResult::fail('INVALID_PARAMS', 'Payload must be an object');
        }

        $payloadMap = $payload ?? [];
        $unknownName = $this->firstUnknownParameterName($handleMethod, $payloadMap);
        if ($unknownName !== null) {
            return ParameterBindResult::fail('INVALID_PARAMS', 'Unknown parameter: ' . $unknownName);
        }

        return $this->bindAllParameters($handleMethod, $payloadMap);
    }

    /**
     * Проверяет, что payload — JSON-объект или null.
     *
     * @param mixed $payload Тело запроса.
     *
     * @return bool true, если payload можно разбирать как объект полей.
     */
    private function isObjectPayload(mixed $payload): bool
    {
        if ($payload === null) {
            return true;
        }

        if (!is_array($payload)) {
            return false;
        }

        return $payload === [] || !array_is_list($payload);
    }

    /**
     * Возвращает первое лишнее поле JSON.
     *
     * @param ReflectionMethod $handleMethod Метод handle.
     * @param array<mixed> $payloadMap Поля JSON.
     *
     * @return string|null Имя лишнего поля.
     */
    private function firstUnknownParameterName(ReflectionMethod $handleMethod, array $payloadMap): ?string
    {
        $declaredNames = [];
        foreach ($handleMethod->getParameters() as $parameter) {
            $declaredNames[$parameter->getName()] = true;
        }

        foreach (array_keys($payloadMap) as $payloadKey) {
            if (!is_string($payloadKey) || !isset($declaredNames[$payloadKey])) {
                return is_string($payloadKey) ? $payloadKey : '0';
            }
        }

        return null;
    }

    /**
     * Привязывает все параметры метода.
     *
     * @param ReflectionMethod $handleMethod Метод handle.
     * @param array<string, mixed> $payloadMap Поля JSON.
     *
     * @return ParameterBindResult Аргументы или ошибка.
     */
    private function bindAllParameters(ReflectionMethod $handleMethod, array $payloadMap): ParameterBindResult
    {
        $boundArguments = [];
        foreach ($handleMethod->getParameters() as $parameter) {
            $boundParameter = $this->bindParameter($parameter, $payloadMap);
            if (!$boundParameter->isValid) {
                return ParameterBindResult::fail('INVALID_PARAMS', $boundParameter->errorMessage);
            }

            $boundArguments[] = $boundParameter->value;
        }

        return ParameterBindResult::ok($boundArguments);
    }

    /**
     * Привязывает один параметр handle.
     *
     * @param ReflectionParameter $parameter Параметр метода.
     * @param array<string, mixed> $payloadMap Поля JSON.
     *
     * @return BoundArgument Значение или ошибка.
     */
    private function bindParameter(ReflectionParameter $parameter, array $payloadMap): BoundArgument
    {
        if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
            return BoundArgument::invalid('Unsupported parameter: ' . $parameter->getName());
        }

        if (!array_key_exists($parameter->getName(), $payloadMap)) {
            return $this->missingParameter($parameter);
        }

        return $this->typedValue($parameter, $payloadMap[$parameter->getName()]);
    }

    /**
     * Обрабатывает отсутствие поля в JSON.
     *
     * @param ReflectionParameter $parameter Параметр метода.
     *
     * @return BoundArgument Default, либо ошибка отсутствия.
     */
    private function missingParameter(ReflectionParameter $parameter): BoundArgument
    {
        if ($parameter->isDefaultValueAvailable()) {
            return BoundArgument::valid($parameter->getDefaultValue());
        }

        return BoundArgument::invalid('Missing parameter: ' . $parameter->getName());
    }

    /**
     * Приводит присутствующее значение к типу параметра.
     *
     * @param ReflectionParameter $parameter Параметр метода.
     * @param mixed $value Значение из JSON.
     *
     * @return BoundArgument Приведённое значение или ошибка.
     */
    private function typedValue(ReflectionParameter $parameter, mixed $value): BoundArgument
    {
        if ($value === null) {
            return $this->nullValue($parameter);
        }

        return $this->nonNullValue($parameter, $value);
    }

    /**
     * Приводит ненулевое значение к типу параметра.
     *
     * @param ReflectionParameter $parameter Параметр метода.
     * @param mixed $value Значение из JSON.
     *
     * @return BoundArgument Приведённое значение или ошибка.
     */
    private function nonNullValue(ReflectionParameter $parameter, mixed $value): BoundArgument
    {
        $parameterType = $parameter->getType();
        if ($parameterType === null) {
            return BoundArgument::valid($value);
        }

        if (!$parameterType instanceof ReflectionNamedType) {
            return BoundArgument::invalid('Unsupported parameter: ' . $parameter->getName());
        }

        return $this->typeMatcher->match($parameterType, $value, $parameter->getName());
    }

    /**
     * Принимает JSON null только для nullable-параметра.
     *
     * @param ReflectionParameter $parameter Параметр метода.
     *
     * @return BoundArgument null или ошибка.
     */
    private function nullValue(ReflectionParameter $parameter): BoundArgument
    {
        if ($parameter->allowsNull()) {
            return BoundArgument::valid(null);
        }

        return BoundArgument::invalid('Invalid parameter: ' . $parameter->getName());
    }
}
