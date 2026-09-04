<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use DateTimeImmutable;
use DateTimeZone;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Строка диалога Y-m-d → unix UTC 00:00.
 */
final class YmdDateParser
{
    /**
     * Пусто или null — нет даты; иначе календарный день UTC.
     *
     * @param string|null $calendarDay Y-m-d или пусто.
     *
     * @return DateTime|null Момент.
     *
     * @throws UserInvalidException Если формат кривой.
     */
    public function parseNullable(?string $calendarDay): ?DateTime
    {
        if ($calendarDay === null) {
            return null;
        }

        $trimmedDay = trim($calendarDay);
        if ($trimmedDay === '') {
            return null;
        }

        $parsedDay = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $trimmedDay,
            new DateTimeZone('UTC'),
        );
        if ($parsedDay === false || $parsedDay->format('Y-m-d') !== $trimmedDay) {
            throw new UserInvalidException('User deactivatedUntil is invalid');
        }

        return DateTime::fromUnix($parsedDay->getTimestamp());
    }
}
