<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Roleplay\Lore\Dto\Action\ArchiveLoreWorldInput;
use Mifrial\Roleplay\Lore\Dto\Action\FindLoreWorldPageInput;
use Mifrial\Roleplay\Lore\Dto\Action\GetLoreWorldInput;
use Mifrial\Roleplay\Lore\Dto\Action\SaveLoreTreeInput;
use Mifrial\Roleplay\Lore\Dto\Action\SaveLoreWorldInput;
use Mifrial\Roleplay\Lore\Dto\LoreWorldRecord;

/**
 * HTTP-сценарии мира кодекса.
 */
final class LoreWorldHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param LoreAccessService $accessService Доступ.
     * @param LoreWorlds $worlds Сценарии мира.
     * @param LoreViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreAccessService $accessService,
        private readonly LoreWorlds $worlds,
        private readonly LoreViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Страница миров.
     *
     * @param FindLoreWorldPageInput $input JSON.
     *
     * @return array<string, mixed> Страница.
     */
    public function findPage(FindLoreWorldPageInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $page = $this->worlds->findPage(
            $actor,
            $input->limit,
            $input->offset,
            $input->q->isPresent() ? $input->q->getValue() : null,
            $input->state->isPresent() ? $input->state->getValue() : 'active',
            $input->visibility->isPresent() ? $input->visibility->getValue() : null,
        );
        $items = [];
        foreach ($page->getItems() as $worldRecord) {
            $items[] = $this->viewAssembler->assembleWorld(
                $worldRecord,
                $this->accessService->canEditWorld($actor, $worldRecord),
            );
        }

        return ['items' => $items, 'total' => $page->getTotal()];
    }

    /**
     * Мир с деревом.
     *
     * @param GetLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Мир.
     */
    public function get(GetLoreWorldInput $input): array
    {
        $actor = $this->accessService->requireActor();

        return $this->viewAssembler->assembleWorldDetail($this->worlds->detail($actor, $input->code));
    }

    /**
     * Создаёт или обновляет мир.
     *
     * @param SaveLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Мир.
     */
    public function save(SaveLoreWorldInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $worldRecord = $this->worlds->saveWorld(
            $actor,
            $input->world,
            $input->version->isPresent() ? $input->version->getValue() : null,
        );

        return $this->viewAssembler->assembleWorld($worldRecord, $this->canEdit($actor, $worldRecord));
    }

    /**
     * Архивирует мир.
     *
     * @param ArchiveLoreWorldInput $input JSON.
     *
     * @return array<string, mixed> Мир.
     */
    public function archive(ArchiveLoreWorldInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $worldRecord = $this->worlds->archive(
            $actor,
            $input->code,
            $input->archived,
            $input->expectedVersion->isPresent() ? $input->expectedVersion->getValue() : null,
        );

        return $this->viewAssembler->assembleWorld($worldRecord, $this->canEdit($actor, $worldRecord));
    }

    /**
     * Заменяет дерево разделов.
     *
     * @param SaveLoreTreeInput $input JSON.
     *
     * @return array<string, mixed> Мир.
     */
    public function saveTree(SaveLoreTreeInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $detail = $this->worlds->saveTree(
            $actor,
            $input->code,
            $input->sections,
            $input->expectedWorldVersion->isPresent() ? $input->expectedWorldVersion->getValue() : null,
        );

        return $this->viewAssembler->assembleWorldDetail($detail);
    }

    /**
     * Правка мира доступна.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     *
     * @return bool Флаг.
     */
    private function canEdit(RequestActor $actor, LoreWorldRecord $worldRecord): bool
    {
        return $this->accessService->canEditWorld($actor, $worldRecord);
    }
}
