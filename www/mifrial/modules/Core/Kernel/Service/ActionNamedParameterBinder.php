<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Dto\BoundArgument;
use Mifrial\Core\Kernel\Dto\ParameterBindResult;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Value\Optional\OptionalValue;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Биндинг имён параметров метода или конструктора DTO к JSON.
 */
final class ActionNamedParameterBinder
{
    /**
     * Создаёт биндер именованных параметров.
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
     * Биндит имена параметров метода к JSON.
     *
     * @param ReflectionMethod $method handle или конструктор DTO.
     * @param array<mixed> $payloadMap Поля JSON.
     *
     * @return ParameterBindResult Аргументы или ошибка.
     */
    public function bind(ReflectionMethod $method, array $payloadMap): ParameterBindResult
    {
        $unknownName = $this->firstUnknownParameterName($method, $payloadMap);
        if ($unknownName !== null) {
            return ParameterBindResult::fail('INVALID_PARAMS', 'Unknown parameter: ' . $unknownName);
        }

        return $this->bindAllParameters($method, $payloadMap);
    }

    /**
     * Возвращает первое лишнее поле JSON.
     *
     * @param ReflectionMethod $method Метод.
     * @param array<mixed> $payloadMap Поля JSON.
     *
     * @return string|null Имя лишнего поля.
     */
    private function firstUnknownParameterName(ReflectionMethod $method, array $payloadMap): ?string
    {
        $declaredNames = [];
        foreach ($method->getParameters() as $parameter) {
            $declaredNames[$parameter->getName()] = true;
        }

        return $this->firstUnknownKey($declaredNames, $payloadMap);
    }

    /**
     * Первое имя JSON не из карты параметров.
     *
     * @param array<string, true> $declaredNames Имена.
     * @param array<mixed> $payloadMap JSON.
     *
     * @return string|null Лишний ключ.
     */
    private function firstUnknownKey(array $declaredNames, array $payloadMap): ?string
    {
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
     * @param ReflectionMethod $method Метод.
     * @param array<string, mixed> $payloadMap Поля JSON.
     *
     * @return ParameterBindResult Аргументы или ошибка.
     */
    private function bindAllParameters(ReflectionMethod $method, array $payloadMap): ParameterBindResult
    {
        $boundArguments = [];
        foreach ($method->getParameters() as $parameter) {
            $boundParameter = $this->bindParameter($parameter, $payloadMap);
            if (!$boundParameter->isValid) {
                return ParameterBindResult::fail('INVALID_PARAMS', $boundParameter->errorMessage);
            }

            $boundArguments[] = $boundParameter->value;
        }

        return ParameterBindResult::ok($boundArguments);
    }

    /**
     * Привязывает один параметр.
     *
     * @param ReflectionParameter $parameter Параметр.
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
     * @param ReflectionParameter $parameter Параметр.
     *
     * @return BoundArgument Default, absent или ошибка.
     */
    private function missingParameter(ReflectionParameter $parameter): BoundArgument
    {
        $optionalClass = $this->optionalValueClass($parameter);
        if ($optionalClass !== null) {
            return BoundArgument::valid($optionalClass::absent());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return BoundArgument::valid($parameter->getDefaultValue());
        }

        return BoundArgument::invalid('Missing parameter: ' . $parameter->getName());
    }

    /**
     * Приводит присутствующее значение к типу параметра.
     *
     * @param ReflectionParameter $parameter Параметр.
     * @param mixed $value Значение из JSON.
     *
     * @return BoundArgument Приведённое значение или ошибка.
     */
    private function typedValue(ReflectionParameter $parameter, mixed $value): BoundArgument
    {
        $optionalBound = $this->bindOptionalJson($parameter, $value);
        if ($optionalBound instanceof BoundArgument) {
            return $optionalBound;
        }

        if ($value === null) {
            return $this->nullValue($parameter);
        }

        return $this->nonNullValue($parameter, $value);
    }

    /**
     * Гидрирует лист OptionalValue.
     *
     * @param ReflectionParameter $parameter Параметр.
     * @param mixed $value JSON.
     *
     * @return BoundArgument|null Результат или null, если не Optional.
     *
     * @throws KernelException Если Optional бросил не OPTIONAL_JSON.
     */
    private function bindOptionalJson(ReflectionParameter $parameter, mixed $value): ?BoundArgument
    {
        $optionalClass = $this->optionalValueClass($parameter);
        if ($optionalClass === null) {
            return null;
        }

        try {
            return BoundArgument::valid($optionalClass::fromJson($value));
        } catch (KernelException $exception) {
            if ($exception->getErrorCode() !== 'OPTIONAL_JSON') {
                throw $exception;
            }

            return BoundArgument::invalid('Invalid parameter: ' . $parameter->getName());
        }
    }

    /**
     * Class-string листа OptionalValue.
     *
     * @param ReflectionParameter $parameter Параметр.
     *
     * @return class-string<OptionalValue>|null Класс листа.
     */
    private function optionalValueClass(ReflectionParameter $parameter): ?string
    {
        $parameterType = $parameter->getType();
        if (!$parameterType instanceof ReflectionNamedType || $parameterType->isBuiltin()) {
            return null;
        }

        $className = $parameterType->getName();
        if ($className === OptionalValue::class || !is_a($className, OptionalValue::class, true)) {
            return null;
        }

        return $className;
    }

    /**
     * Приводит ненулевое значение к типу параметра.
     *
     * @param ReflectionParameter $parameter Параметр.
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
     * @param ReflectionParameter $parameter Параметр.
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
