<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Exception;

use Throwable;

/**
 * Голова пространства уже не та, от которой собран состав.
 */
final class SpaceConflictException extends SpaceException
{
    /**
     * Создаёт отказ публикации.
     *
     * @param int $expectedRevision Номер, от которого собран состав.
     * @param int $actualRevision Текущая голова.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly int $expectedRevision,
        private readonly int $actualRevision,
        ?Throwable $previous = null,
    ) {
        parent::__construct('SPACE_CONFLICT', 'Revision head has changed', $previous);
    }

    /**
     * Номер, от которого собран состав.
     *
     * @return int Номер.
     */
    public function getExpectedRevision(): int
    {
        return $this->expectedRevision;
    }

    /**
     * Текущая голова пространства.
     *
     * @return int Номер.
     */
    public function getActualRevision(): int
    {
        return $this->actualRevision;
    }
}
