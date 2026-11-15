<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Roleplay\Lore\Dto\Action\ExportLoreWorldInput;
use Mifrial\Roleplay\Lore\Dto\Action\ImportLoreWorldInput;
use Mifrial\Roleplay\Lore\Dto\LoreImportReport;
use Mifrial\Roleplay\Lore\Dto\LoreImportRequest;

/**
 * HTTP-сценарии переноса кодекса между стендами.
 */
final class LoreTransferHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param LoreAccessService $accessService Доступ.
     * @param LoreExportService $exportService Экспорт.
     * @param LoreImportService $importService Импорт.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreAccessService $accessService,
        private readonly LoreExportService $exportService,
        private readonly LoreImportService $importService,
    ) {
    }

    /**
     * Отдаёт документ мира.
     *
     * @param ExportLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> `powerscale.lore`.
     */
    public function export(ExportLoreWorldInput $input): array
    {
        $actor = $this->accessService->requireActor();

        return $this->exportService->export($actor, $input->worldCode);
    }

    /**
     * Принимает документ мира или делает сухой прогон.
     *
     * @param ImportLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Отчёт.
     */
    public function import(ImportLoreWorldInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $report = $this->importService->import($actor, new LoreImportRequest(
            $input->payload,
            $input->mode->isPresent() ? $input->mode->getValue() : 'new',
            $input->targetWorldCode->isPresent() ? $input->targetWorldCode->getValue() : null,
            $input->conflictPolicy->isPresent() ? $input->conflictPolicy->getValue() : 'skip',
            $input->dryRun,
        ));

        return $this->assembleReport($report);
    }

    /**
     * JSON отчёта импорта.
     *
     * @param LoreImportReport $report Отчёт.
     *
     * @return array<string, mixed> JSON.
     */
    private function assembleReport(LoreImportReport $report): array
    {
        return [
            'worldCode' => $report->getWorldCode(),
            'dryRun' => $report->isDryRun(),
            'created' => $report->getCreated(),
            'updated' => $report->getUpdated(),
            'skipped' => $report->getSkipped(),
            'warnings' => $report->getWarnings(),
        ];
    }
}
