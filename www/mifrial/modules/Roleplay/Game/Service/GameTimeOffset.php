<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Шесть единиц GameTime в минуты и обратно. Ход и секунда не хранятся.
 */
final class GameTimeOffset
{
    private const MINUTES_PER_HOUR = 60;

    private const HOURS_PER_DAY = 30;

    private const DAYS_PER_DECADE = 10;

    private const DECADES_PER_MONTH = 3;

    private const MONTHS_PER_YEAR = 10;

    private const MINUTES_PER_DAY = self::MINUTES_PER_HOUR * self::HOURS_PER_DAY;

    private const MINUTES_PER_DECADE = self::MINUTES_PER_DAY * self::DAYS_PER_DECADE;

    private const MINUTES_PER_MONTH = self::MINUTES_PER_DECADE * self::DECADES_PER_MONTH;

    private const MINUTES_PER_YEAR = self::MINUTES_PER_MONTH * self::MONTHS_PER_YEAR;

    private const MAX_MINUTES = 2147483647;

    /**
     * Складывает объект времени в минуты.
     *
     * @param array<mixed> $offset Шесть единиц.
     *
     * @return int Минуты от эпохи.
     *
     * @throws GameInvalidException Если форма или сумма неверны.
     */
    public function toMinutes(array $offset): int
    {
        $this->assertShape($offset);
        $total = 0;
        $total = $this->add($total, $this->scale($this->unit($offset, 'years'), self::MINUTES_PER_YEAR));
        $total = $this->add($total, $this->scale($this->unit($offset, 'months'), self::MINUTES_PER_MONTH));
        $total = $this->add($total, $this->scale($this->unit($offset, 'decades'), self::MINUTES_PER_DECADE));
        $total = $this->add($total, $this->scale($this->unit($offset, 'days'), self::MINUTES_PER_DAY));
        $total = $this->add($total, $this->scale($this->unit($offset, 'hours'), self::MINUTES_PER_HOUR));
        $total = $this->add($total, $this->unit($offset, 'minutes'));

        return $total;
    }

    /**
     * Раскладывает минуты на канонические единицы.
     *
     * @param int $minutes Смещение.
     *
     * @return array{years: int, months: int, decades: int, days: int, hours: int, minutes: int} Единицы.
     */
    public function toParts(int $minutes): array
    {
        $rest = $minutes;
        $years = intdiv($rest, self::MINUTES_PER_YEAR);
        $rest -= $years * self::MINUTES_PER_YEAR;
        $months = intdiv($rest, self::MINUTES_PER_MONTH);
        $rest -= $months * self::MINUTES_PER_MONTH;
        $decades = intdiv($rest, self::MINUTES_PER_DECADE);
        $rest -= $decades * self::MINUTES_PER_DECADE;
        $days = intdiv($rest, self::MINUTES_PER_DAY);
        $rest -= $days * self::MINUTES_PER_DAY;
        $hours = intdiv($rest, self::MINUTES_PER_HOUR);
        $rest -= $hours * self::MINUTES_PER_HOUR;

        return [
            'years' => $years,
            'months' => $months,
            'decades' => $decades,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $rest,
        ];
    }

    /**
     * Требует ровно шесть ключей.
     *
     * @param array<mixed> $offset Объект.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ лишний или единицы нет.
     */
    private function assertShape(array $offset): void
    {
        $names = ['years', 'months', 'decades', 'days', 'hours', 'minutes'];
        foreach (array_keys($offset) as $name) {
            if (!in_array($name, $names, true)) {
                throw new GameInvalidException('Game time offset is invalid');
            }
        }

        foreach ($names as $name) {
            if (!array_key_exists($name, $offset)) {
                throw new GameInvalidException('Game time offset is invalid');
            }
        }
    }

    /**
     * Читает неотрицательное целое.
     *
     * @param array<mixed> $offset Объект.
     * @param string $name Ключ.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если не целое или отрицательное.
     */
    private function unit(array $offset, string $name): int
    {
        $value = $offset[$name];
        if (!is_int($value) || $value < 0) {
            throw new GameInvalidException('Game time offset is invalid');
        }

        return $value;
    }

    /**
     * Умножает на вес единицы, не выходя за signed INT.
     *
     * @param int $value Число единиц.
     * @param int $factor Минут в единице.
     *
     * @return int Минуты.
     *
     * @throws GameInvalidException Если произведение не влезает.
     */
    private function scale(int $value, int $factor): int
    {
        if ($value > intdiv(self::MAX_MINUTES, $factor)) {
            throw new GameInvalidException('Game time offset is invalid');
        }

        return $value * $factor;
    }

    /**
     * Складывает, не выходя за signed INT.
     *
     * @param int $total Уже накоплено.
     * @param int $part Слагаемое.
     *
     * @return int Сумма.
     *
     * @throws GameInvalidException Если сумма не влезает.
     */
    private function add(int $total, int $part): int
    {
        if ($part < 0 || $part > self::MAX_MINUTES || $total > self::MAX_MINUTES - $part) {
            throw new GameInvalidException('Game time offset is invalid');
        }

        return $total + $part;
    }
}
