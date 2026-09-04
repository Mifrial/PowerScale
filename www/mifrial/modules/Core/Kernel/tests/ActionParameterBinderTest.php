<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Dto\ParameterBindResult;
use Mifrial\Core\Kernel\Service\ActionParameterBinder;
use Mifrial\Core\Kernel\Tests\Fixture\ParameterBindingInputTarget;
use Mifrial\Core\Kernel\Tests\Fixture\ParameterBindingTarget;
use Mifrial\Core\Kernel\Tests\Fixture\SampleColor;
use Mifrial\Core\Kernel\Tests\Fixture\SampleUpdateInput;
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
     * Плоский JSON на IActionInput; Optional absent и present.
     *
     * @return void
     */
    public function testBindsActionInputFromFlatJson(): void
    {
        $withName = $this->bindInput('handle', ['id' => 4, 'name' => 'Ann']);
        $withoutName = $this->bindInput('handle', ['id' => 4]);
        self::assertTrue($withName->isOk());
        $named = $withName->arguments()[0];
        self::assertInstanceOf(SampleUpdateInput::class, $named);
        self::assertSame(4, $named->id);
        self::assertTrue($named->name->isPresent());
        self::assertSame('Ann', $named->name->getValue());
        self::assertTrue($withoutName->isOk());
        $skipped = $withoutName->arguments()[0];
        self::assertInstanceOf(SampleUpdateInput::class, $skipped);
        self::assertFalse($skipped->name->isPresent());
        self::assertFalse($skipped->active->isPresent());
        $nullActive = $this->bindInput('handle', ['id' => 1, 'active' => null]);
        self::assertSame('Invalid parameter: active', $nullActive->errorResponse()->toArray()['error']['message']);
    }

    /**
     * Extra key, missing id, JSON null у OptionalString, отказ bool-null.
     *
     * @return void
     */
    public function testActionInputRejectsUnknownMissingAndNullRules(): void
    {
        $extra = $this->bindInput('handle', ['id' => 1, 'name' => 'A', 'extra' => true]);
        $missing = $this->bindInput('handle', []);
        $nullName = $this->bindInput('handle', ['id' => 1, 'name' => null]);
        self::assertSame('Unknown parameter: extra', $extra->errorResponse()->toArray()['error']['message']);
        self::assertSame('Missing parameter: id', $missing->errorResponse()->toArray()['error']['message']);
        self::assertTrue($nullName->isOk());
        $input = $nullName->arguments()[0];
        self::assertInstanceOf(SampleUpdateInput::class, $input);
        self::assertTrue($input->name->isPresent());
        self::assertNull($input->name->getValue());
    }

    /**
     * Смесь скаляра и DTO; DateTime на handle.
     *
     * @return void
     */
    public function testRejectsMixedInputAndDateTimeHandle(): void
    {
        $mix = $this->bindInput('withMix', ['id' => 1, 'name' => 'A']);
        $dateTime = $this->bindInput('withDateTime', ['moment' => 1]);
        self::assertSame('Unsupported parameter: input', $mix->errorResponse()->toArray()['error']['message']);
        self::assertSame('Unsupported parameter: moment', $dateTime->errorResponse()->toArray()['error']['message']);
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

    /**
     * Биндит методы фикстуры с IActionInput.
     *
     * @param string $methodName Имя метода.
     * @param mixed $payload JSON.
     *
     * @return ParameterBindResult Результат.
     */
    private function bindInput(string $methodName, mixed $payload): ParameterBindResult
    {
        $binder = new ActionParameterBinder();
        $handleMethod = new ReflectionMethod(ParameterBindingInputTarget::class, $methodName);

        return $binder->bind($handleMethod, $payload);
    }
}
