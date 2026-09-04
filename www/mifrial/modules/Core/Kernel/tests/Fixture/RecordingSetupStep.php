<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Interface\Service\ISetupStep;

/**
 * Data-шаг, пишущий id в лог.
 */
final class RecordingSetupStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param string $stepId Стабильный id.
     * @param array<int, string> $log Журнал вызовов (по ссылке снаружи).
     *
     * @return void
     */
    public function __construct(
        private readonly string $stepId,
        private array &$log,
    ) {
    }

    /**
     * Возвращает id шага.
     *
     * @return string Ключ.
     */
    public function getId(): string
    {
        return $this->stepId;
    }

    /**
     * Пишет id в лог.
     *
     * @return void
     */
    public function run(): void
    {
        $this->log[] = $this->stepId;
    }
}
