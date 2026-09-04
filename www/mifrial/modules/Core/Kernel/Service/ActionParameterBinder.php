<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Dto\ParameterBindResult;
use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Биндинг JSON на параметры handle или конструктор IActionInput.
 */
final class ActionParameterBinder
{
    private readonly ActionNamedParameterBinder $namedBinder;

    /**
     * Создаёт биндер параметров действия.
     *
     * @param ActionParameterTypeMatcher $typeMatcher Сопоставление JSON-типов.
     *
     * @return void
     */
    public function __construct(
        ActionParameterTypeMatcher $typeMatcher = new ActionParameterTypeMatcher(),
    ) {
        $this->namedBinder = new ActionNamedParameterBinder($typeMatcher);
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
        $shapeError = $this->actionInputShapeError($handleMethod);
        if ($shapeError !== null) {
            return ParameterBindResult::fail('INVALID_PARAMS', $shapeError);
        }

        $inputClass = $this->soleActionInputClass($handleMethod);

        return $inputClass === null
            ? $this->namedBinder->bind($handleMethod, $payloadMap)
            : $this->bindActionInput($handleMethod, $inputClass, $payloadMap);
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
     * Отвергает смесь IActionInput со скалярами и nullable input.
     *
     * @param ReflectionMethod $handleMethod Метод handle.
     *
     * @return string|null Сообщение или null.
     */
    private function actionInputShapeError(ReflectionMethod $handleMethod): ?string
    {
        $inputParameter = $this->firstActionInputParameter($handleMethod);
        if ($inputParameter === null) {
            return null;
        }

        if (count($handleMethod->getParameters()) !== 1
            || $inputParameter->allowsNull()
            || $inputParameter->isDefaultValueAvailable()
        ) {
            return 'Unsupported parameter: ' . $inputParameter->getName();
        }

        return null;
    }

    /**
     * Class-string единственного IActionInput, если форма верна.
     *
     * @param ReflectionMethod $handleMethod Метод handle.
     *
     * @return class-string<IActionInput>|null Класс DTO.
     */
    private function soleActionInputClass(ReflectionMethod $handleMethod): ?string
    {
        $inputParameter = $this->firstActionInputParameter($handleMethod);
        if ($inputParameter === null || count($handleMethod->getParameters()) !== 1) {
            return null;
        }

        $parameterType = $inputParameter->getType();
        if (!$parameterType instanceof ReflectionNamedType || $parameterType->isBuiltin()) {
            return null;
        }

        return $parameterType->getName();
    }

    /**
     * Первый параметр-маркер IActionInput.
     *
     * @param ReflectionMethod $handleMethod Метод handle.
     *
     * @return ReflectionParameter|null Параметр или null.
     */
    private function firstActionInputParameter(ReflectionMethod $handleMethod): ?ReflectionParameter
    {
        foreach ($handleMethod->getParameters() as $parameter) {
            if ($this->isActionInputType($parameter)) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Параметр — IActionInput.
     *
     * @param ReflectionParameter $parameter Параметр.
     *
     * @return bool true, если маркер.
     */
    private function isActionInputType(ReflectionParameter $parameter): bool
    {
        $parameterType = $parameter->getType();
        if (!$parameterType instanceof ReflectionNamedType || $parameterType->isBuiltin()) {
            return false;
        }

        return is_a($parameterType->getName(), IActionInput::class, true);
    }

    /**
     * Гидрирует DTO из плоского JSON.
     *
     * @param ReflectionMethod $handleMethod Метод handle.
     * @param class-string<IActionInput> $inputClass Класс DTO.
     * @param array<mixed> $payloadMap Поля JSON.
     *
     * @return ParameterBindResult Один аргумент DTO.
     */
    private function bindActionInput(
        ReflectionMethod $handleMethod,
        string $inputClass,
        array $payloadMap,
    ): ParameterBindResult {
        $inputReflection = new ReflectionClass($inputClass);
        $prepared = $this->preparedInputConstructor($handleMethod, $inputReflection, $payloadMap);
        if (!$prepared->isOk()) {
            return $prepared;
        }

        return ParameterBindResult::ok([$inputReflection->newInstanceArgs($prepared->arguments())]);
    }

    /**
     * Конструктор DTO или отказ формы класса.
     *
     * @param ReflectionMethod $handleMethod Метод handle.
     * @param ReflectionClass $inputReflection Класс DTO.
     * @param array<mixed> $payloadMap Поля JSON.
     *
     * @return ParameterBindResult Аргументы ctor или ошибка.
     */
    private function preparedInputConstructor(
        ReflectionMethod $handleMethod,
        ReflectionClass $inputReflection,
        array $payloadMap,
    ): ParameterBindResult {
        $handleParameter = $handleMethod->getParameters()[0];
        if (!$inputReflection->isFinal() || !$inputReflection->isInstantiable()) {
            return ParameterBindResult::fail(
                'INVALID_PARAMS',
                'Unsupported parameter: ' . $handleParameter->getName(),
            );
        }

        $constructor = $inputReflection->getConstructor();
        if ($constructor === null) {
            return $this->emptyConstructorInput($inputReflection, $payloadMap);
        }

        return $this->namedBinder->bind($constructor, $payloadMap);
    }

    /**
     * DTO без конструктора: только пустое тело.
     *
     * @param ReflectionClass $inputReflection Класс DTO.
     * @param array<mixed> $payloadMap Поля JSON.
     *
     * @return ParameterBindResult DTO или extra key.
     */
    private function emptyConstructorInput(ReflectionClass $inputReflection, array $payloadMap): ParameterBindResult
    {
        if ($payloadMap === []) {
            return ParameterBindResult::ok([]);
        }

        $payloadKey = array_key_first($payloadMap);
        $unknownName = is_string($payloadKey) ? $payloadKey : '0';

        return ParameterBindResult::fail('INVALID_PARAMS', 'Unknown parameter: ' . $unknownName);
    }
}
