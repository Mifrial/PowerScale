<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Реестр data-шагов: строки `setup_step`.
 */
final class SetupStepRepository
{
    /**
     * Создаёт репозиторий реестра.
     *
     * @param IOpenedRecords $stepRecords Строки `setup_step`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $stepRecords,
    ) {
    }

    /**
     * Проверяет, есть ли шаг в этой базе.
     *
     * @param string $stepKey Стабильный id шага.
     *
     * @return bool true, если шаг уже записан.
     */
    public function has(string $stepKey): bool
    {
        $row = $this->stepRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['step_key' => $stepKey],
            'limit' => 1,
        ]));

        return $row !== null;
    }

    /**
     * Записывает успешный шаг.
     *
     * @param string $stepKey Стабильный id шага.
     *
     * @return void
     */
    public function markApplied(string $stepKey): void
    {
        $this->stepRecords->add(['step_key' => $stepKey]);
    }
}
