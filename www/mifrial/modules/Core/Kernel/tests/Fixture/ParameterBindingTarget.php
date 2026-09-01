<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use stdClass;

final class ParameterBindingTarget implements IActionHandler
{
    /**
     * Возвращает переданные скаляры.
     *
     * @param int $id Идентификатор.
     * @param string $code Код.
     *
     * @return array{id: int, code: string} Аргументы.
     */
    public function handle(int $id, string $code): array
    {
        return ['id' => $id, 'code' => $code];
    }

    /**
     * Параметр со значением по умолчанию.
     *
     * @param string $code Код.
     *
     * @return array{code: string} Код.
     */
    public function withDefault(string $code = 's1'): array
    {
        return ['code' => $code];
    }

    /**
     * Nullable-параметр.
     *
     * @param int|null $id Идентификатор.
     *
     * @return array{id: int|null} Идентификатор.
     */
    public function withNullable(?int $id): array
    {
        return ['id' => $id];
    }

    /**
     * Параметр backed enum.
     *
     * @param SampleColor $color Цвет.
     *
     * @return array{color: string} Цвет.
     */
    public function withColor(SampleColor $color): array
    {
        return ['color' => $color->value];
    }

    /**
     * Числовой и булев параметры.
     *
     * @param float $amount Сумма.
     * @param bool $flag Признак.
     *
     * @return array{amount: float, flag: bool} Числа.
     */
    public function withFloatAndBool(float $amount, bool $flag): array
    {
        return ['amount' => $amount, 'flag' => $flag];
    }

    /**
     * Массив.
     *
     * @param array<int, mixed> $ids Список.
     *
     * @return array{ids: array<int, mixed>} Список.
     */
    public function withList(array $ids): array
    {
        return ['ids' => $ids];
    }

    /**
     * Неподдерживаемый объектный тип.
     *
     * @param stdClass $payload Объект.
     *
     * @return array{type: string} Имя класса.
     */
    public function withObject(stdClass $payload): array
    {
        return ['type' => $payload::class];
    }
}
