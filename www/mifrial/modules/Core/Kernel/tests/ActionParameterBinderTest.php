<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Dto\ParameterBindResult;
use Mifrial\Core\Kernel\Service\ActionParameterBinder;
use Mifrial\Core\Kernel\Tests\Fixture\ParameterBindingTarget;
use Mifrial\Core\Kernel\Tests\Fixture\SampleColor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ActionParameterBinderTest extends TestCase
{
    /**
     * Проверяет биндинг int и string по именам полей.
     *
     * @return void
     */
    public function testBindsNamedScalars(): void
    {
        $named = $this->bind('handle', ['code' => 's1', 'id' => 1]);
        $wholeNumber = $this->bind('handle', ['id' => 1.0, 'code' => 's1']);

        self::assertTrue($named->isOk());
        self::assertSame([1, 's1'], $named->arguments());
        self::assertTrue($wholeNumber->isOk());
        self::assertSame([1, 's1'], $wholeNumber->arguments());
    }

    /**
     * Отклоняет строку вместо int.
     *
     * @return void
     */
    public function testRejectsStringForInt(): void
    {
        $result = $this->bind('handle', ['id' => '1', 'code' => 's1']);

        self::assertFalse($result->isOk());
        self::assertSame('INVALID_PARAMS', $result->errorResponse()->toArray()['error']['code']);
    }

    /**
     * Отклоняет лишнее поле JSON.
     *
     * @return void
     */
    public function testRejectsUnknownField(): void
    {
        $result = $this->bind('handle', ['id' => 1, 'code' => 's1', 'extra' => true]);

        self::assertSame('Unknown parameter: extra', $result->errorResponse()->toArray()['error']['message']);
    }

    /**
     * Отклоняет отсутствие обязательного поля.
     *
     * @return void
     */
    public function testRejectsMissingRequiredField(): void
    {
        $result = $this->bind('handle', ['id' => 1]);

        self::assertSame('Missing parameter: code', $result->errorResponse()->toArray()['error']['message']);
    }

    /**
     * Подставляет default, если поля нет.
     *
     * @return void
     */
    public function testUsesDefaultWhenFieldMissing(): void
    {
        $result = $this->bind('withDefault', []);

        self::assertTrue($result->isOk());
        self::assertSame(['s1'], $result->arguments());
    }

    /**
     * Принимает null для nullable-параметра.
     *
     * @return void
     */
    public function testAcceptsNullForNullable(): void
    {
        $result = $this->bind('withNullable', ['id' => null]);

        self::assertTrue($result->isOk());
        self::assertSame([null], $result->arguments());
    }

    /**
     * Отклоняет отсутствие nullable без default.
     *
     * @return void
     */
    public function testRejectsMissingNullableWithoutDefault(): void
    {
        $result = $this->bind('withNullable', []);

        self::assertSame('Missing parameter: id', $result->errorResponse()->toArray()['error']['message']);
    }

    /**
     * Биндит backed enum по значению.
     *
     * @return void
     */
    public function testBindsBackedEnum(): void
    {
        $result = $this->bind('withColor', ['color' => 'red']);

        self::assertTrue($result->isOk());
        self::assertSame([SampleColor::Red], $result->arguments());
    }

    /**
     * Принимает JSON-массив, float и bool без приведения строк.
     *
     * @return void
     */
    public function testBindsArrayFloatAndBool(): void
    {
        $floatResult = $this->bind('withFloatAndBool', ['amount' => 1, 'flag' => true]);
        $listResult = $this->bind('withList', ['ids' => [1, 2]]);

        self::assertTrue($floatResult->isOk());
        self::assertSame([1.0, true], $floatResult->arguments());
        self::assertTrue($listResult->isOk());
        self::assertSame([[1, 2]], $listResult->arguments());
    }

    /**
     * Отклоняет JSON-массив вместо объекта и неподдерживаемые типы.
     *
     * @return void
     */
    public function testRejectsListPayloadAndUnsupportedTypes(): void
    {
        $listPayload = $this->bind('handle', [1, 2]);
        $objectType = $this->bind('withObject', ['payload' => ['a' => 1]]);

        self::assertSame('Payload must be an object', $listPayload->errorResponse()->toArray()['error']['message']);
        self::assertSame('Unsupported parameter: payload', $objectType->errorResponse()->toArray()['error']['message']);
    }

    /**
     * Привязывает payload к методу фикстуры.
     *
     * @param string $methodName Имя метода фикстуры.
     * @param mixed $payload JSON-поля.
     *
     * @return ParameterBindResult Результат биндера.
     */
    private function bind(string $methodName, mixed $payload): ParameterBindResult
    {
        $binder = new ActionParameterBinder();
        $handleMethod = new ReflectionMethod(ParameterBindingTarget::class, $methodName);

        return $binder->bind($handleMethod, $payload);
    }
}
