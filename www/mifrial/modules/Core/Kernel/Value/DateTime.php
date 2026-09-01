<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Value;

/**
 * Момент времени как unix UTC без разбора строк.
 */
final class DateTime
{
    /**
     * Создаёт значение из unix-секунд.
     *
     * @param int $unixTimestamp Секунды UTC.
     *
     * @return void
     */
    private function __construct(
        private readonly int $unixTimestamp,
    ) {
    }

    /**
     * Собирает момент из unix-секунд.
     *
     * @param int $unixTimestamp Секунды UTC, включая 0.
     *
     * @return self Момент времени.
     */
    public static function fromUnix(int $unixTimestamp): self
    {
        return new self($unixTimestamp);
    }

    /**
     * Возвращает unix-секунды UTC.
     *
     * @return int Секунды.
     */
    public function toUnix(): int
    {
        return $this->unixTimestamp;
    }
}
