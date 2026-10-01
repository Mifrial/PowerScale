<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Exception;

use Throwable;

/**
 * Черновик собран не от текущей головы мира.
 */
final class RuleSpaceConflictException extends RuleSpaceException
{
    /**
     * Создаёт отказ публикации.
     *
     * @param int $expectedRevision Номер, от которого собран черновик.
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
        parent::__construct(
            'RULESPACE_CONFLICT',
            'Ревизия устарела. Перечитайте срез и проверьте черновик.',
            $previous,
        );
    }

    /**
     * Номер, от которого собран черновик.
     *
     * @return int Номер.
     */
    public function getExpectedRevision(): int
    {
        return $this->expectedRevision;
    }

    /**
     * Текущая голова мира.
     *
     * @return int Номер.
     */
    public function getActualRevision(): int
    {
        return $this->actualRevision;
    }

    /**
     * Номера для клиента.
     *
     * @return array<string, int> Ожидаемая и фактическая ревизии.
     */
    public function getErrorDetails(): array
    {
        return [
            'expectedRevision' => $this->expectedRevision,
            'actualRevision' => $this->actualRevision,
        ];
    }
}
