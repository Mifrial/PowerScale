<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service\Setup;

use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\Kernel\Repository\SetupStepRepository;
use Mifrial\Core\Kernel\Table\SetupStepTable;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Прогон DDL по графу и непройденных data-шагов.
 */
final class ModuleSetupRunner
{
    /**
     * Создаёт прогон.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз таблиц.
     * @param array<int, array{key: string, setup: IModuleSetup}> $moduleSetups Setup модулей.
     * @param TableSetupOrder $tableSetupOrder Сортировка карт.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly array $moduleSetups,
        private readonly TableSetupOrder $tableSetupOrder = new TableSetupOrder(),
    ) {
    }

    /**
     * Сверяет все карты, затем выполняет новые data-шаги.
     *
     * @return void
     *
     * @throws SetupException Если граф или список шагов некорректен.
     */
    public function run(): void
    {
        $sortedSetups = $this->sortedSetups();
        $this->assertUniqueStepIds($sortedSetups);
        foreach ($this->orderedDefinitions() as $definition) {
            $this->applySchema($this->smartTableGateway->open($definition::class)->schema());
        }

        $this->runPendingSteps($sortedSetups);
    }

    /**
     * Сортирует setup модулей по Group/Name.
     *
     * @return array<int, array{key: string, setup: IModuleSetup}> Порядок data-шагов.
     */
    private function sortedSetups(): array
    {
        $sortedSetups = $this->moduleSetups;
        usort(
            $sortedSetups,
            static fn (array $left, array $right): int => $left['key'] <=> $right['key'],
        );

        return $sortedSetups;
    }

    /**
     * Проверяет уникальность id шагов в прогоне.
     *
     * @param array<int, array{key: string, setup: IModuleSetup}> $sortedSetups Setup по ключу.
     *
     * @return void
     *
     * @throws SetupException Если id повторяется.
     */
    private function assertUniqueStepIds(array $sortedSetups): void
    {
        $seenIds = [];
        foreach ($sortedSetups as $moduleSetup) {
            foreach ($moduleSetup['setup']->getDataSteps() as $setupStep) {
                $stepId = $setupStep->getId();
                if (isset($seenIds[$stepId])) {
                    throw new SetupException(
                        'SETUP_STEP_DUPLICATE',
                        'Duplicate setup step id: ' . $stepId,
                    );
                }

                $seenIds[$stepId] = true;
            }
        }
    }

    /**
     * Собирает карты всех setup и сортирует по FK.
     *
     * @return array<int, SmartTableDefinition> Порядок DDL.
     *
     * @throws SetupException Если граф некорректен.
     */
    private function orderedDefinitions(): array
    {
        $tableClasses = [];
        foreach ($this->moduleSetups as $moduleSetup) {
            foreach ($moduleSetup['setup']->getTableClasses() as $tableClass) {
                $tableClasses[] = $tableClass;
            }
        }

        return $this->tableSetupOrder->order($tableClasses);
    }

    /**
     * Создаёт или обновляет одну таблицу.
     *
     * @param IOpenedSchema $openedSchema DDL карты.
     *
     * @return void
     */
    private function applySchema(IOpenedSchema $openedSchema): void
    {
        if ($openedSchema->exists()) {
            $openedSchema->updateTable();

            return;
        }

        $openedSchema->createTable();
    }

    /**
     * Прогоняет непройденные data-шаги уже отсортированных модулей.
     *
     * @param array<int, array{key: string, setup: IModuleSetup}> $sortedSetups Setup по ключу.
     *
     * @return void
     */
    private function runPendingSteps(array $sortedSetups): void
    {
        $registry = new SetupStepRepository(
            $this->smartTableGateway->open(SetupStepTable::class)->records(),
        );
        foreach ($sortedSetups as $moduleSetup) {
            foreach ($moduleSetup['setup']->getDataSteps() as $setupStep) {
                $this->applyStep($registry, $setupStep);
            }
        }
    }

    /**
     * Выполняет шаг, если его ещё нет в реестре.
     *
     * @param SetupStepRepository $registry Реестр.
     * @param ISetupStep $setupStep Шаг.
     *
     * @return void
     */
    private function applyStep(SetupStepRepository $registry, ISetupStep $setupStep): void
    {
        $stepId = $setupStep->getId();
        if ($registry->has($stepId)) {
            return;
        }

        $setupStep->run();
        $registry->markApplied($stepId);
    }
}
