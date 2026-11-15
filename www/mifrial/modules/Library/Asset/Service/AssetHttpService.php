<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Library\Asset\Dto\Action\ArchiveAssetInput;
use Mifrial\Library\Asset\Dto\Action\CreateAssetInput;
use Mifrial\Library\Asset\Dto\Action\FindAssetPageInput;
use Mifrial\Library\Asset\Dto\Action\UpdateAssetInput;
use Mifrial\Library\Asset\Dto\AssetCollectionRecord;
use Mifrial\Library\Asset\Dto\AssetPageQuery;
use Mifrial\Library\Asset\Dto\AssetRecord;
use Mifrial\Library\Asset\Enum\AssetScope;
use Mifrial\Library\Asset\Exception\AssetDeniedException;
use Mifrial\Library\Asset\Repository\AssetCollectionRepository;
use Mifrial\Library\Asset\Repository\AssetItemRepository;

/**
 * HTTP-сценарии библиотеки: страница, создание, переименование и архив.
 */
final class AssetHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard учёток.
     * @param AssetCollectionRepository $collectionRepository Строки коллекций.
     * @param AssetItemRepository $itemRepository Строки ассетов.
     * @param AssetFileResolver $fileResolver Файлы ассетов.
     * @param AssetViewAssembler $viewAssembler JSON-вид.
     * @param AssetInputValidator $inputValidator Проверка полей.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly AssetCollectionRepository $collectionRepository,
        private readonly AssetItemRepository $itemRepository,
        private readonly AssetFileResolver $fileResolver,
        private readonly AssetViewAssembler $viewAssembler,
        private readonly AssetInputValidator $inputValidator,
    ) {
    }

    /**
     * Страница ассетов коллекции.
     *
     * @param FindAssetPageInput $input JSON.
     *
     * @return array<string, mixed> `items` и `total`.
     *
     * @throws ActionException Если актора нет или запрос недопустим.
     */
    public function findPage(FindAssetPageInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $scope = AssetScope::fromInput($input->scope);
        $collectionRecord = $this->requireCollection($input->collectionModule, $input->collectionCode);
        $this->inputValidator->assertPageBounds($input->limit, $input->offset);
        $page = $this->itemRepository->findPage(new AssetPageQuery(
            $collectionRecord->getId(),
            $scope,
            $actor->getUserId(),
            $this->inputValidator->normalizeSearchQuery(
                $input->q->isPresent() ? $input->q->getValue() : null,
            ),
            $input->limit,
            $input->offset,
            $input->hasMeta->isPresent() && $input->hasMeta->getValue(),
        ));
        $fileIds = [];
        foreach ($page->getItems() as $assetRecord) {
            $fileIds[] = $assetRecord->getFileId();
        }

        $fileRecords = $this->fileResolver->findPageFiles($fileIds);
        $items = [];
        foreach ($page->getItems() as $assetRecord) {
            $items[] = $this->viewAssembler->assemble(
                $assetRecord,
                $collectionRecord,
                $fileRecords[$assetRecord->getFileId()] ?? null,
            );
        }

        return ['items' => $items, 'total' => $page->getTotal()];
    }

    /**
     * Добавляет ассет поверх существующего файла.
     *
     * @param CreateAssetInput $input JSON.
     *
     * @return array<string, mixed> Созданный ассет.
     *
     * @throws ActionException Если актора нет или запрос недопустим.
     */
    public function create(CreateAssetInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $scope = AssetScope::fromInput($input->scope);
        $collectionRecord = $this->requireCollection($input->collectionModule, $input->collectionCode);
        if ($scope === AssetScope::Shared && !$actor->hasBypass()) {
            throw new AssetDeniedException('Only a bypass actor can add shared assets');
        }

        $fileRecord = $this->fileResolver->requireImage($input->filePublicId);
        $name = $this->inputValidator->normalizeName(
            $input->name->isPresent() ? $input->name->getValue() : null,
            $fileRecord->getOriginalName(),
        );
        $isShared = $scope === AssetScope::Shared;
        $meta = $input->meta->isPresent()
            ? $this->inputValidator->normalizeMeta($input->meta->getValue())
            : null;
        $assetId = $this->itemRepository->add(
            $collectionRecord->getId(),
            $fileRecord->getId(),
            $isShared,
            $isShared ? null : $actor->getUserId(),
            $name,
            $meta,
        );

        return $this->viewAssembler->assemble(
            $this->itemRepository->getById($assetId),
            $collectionRecord,
            $fileRecord,
        );
    }

    /**
     * Переименовывает ассет.
     *
     * @param UpdateAssetInput $input JSON.
     *
     * @return array<string, mixed> Обновлённый ассет.
     *
     * @throws ActionException Если актора нет или правка запрещена.
     */
    public function update(UpdateAssetInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $assetRecord = $this->itemRepository->getById($input->id);
        $this->assertCanEdit($actor, $assetRecord);
        $name = $this->inputValidator->normalizeName($input->name, '');
        $this->itemRepository->updateName($assetRecord->getId(), $name);
        if ($input->meta->isPresent()) {
            $this->itemRepository->updateMeta(
                $assetRecord->getId(),
                $this->inputValidator->normalizeMeta($input->meta->getValue()),
            );
        }

        $assetRecord = $this->itemRepository->getById($input->id);
        $collectionRecord = $this->collectionRepository->getById($assetRecord->getCollectionId());
        $fileRecords = $this->fileResolver->findPageFiles([$assetRecord->getFileId()]);

        return $this->viewAssembler->assemble(
            $assetRecord,
            $collectionRecord,
            $fileRecords[$assetRecord->getFileId()] ?? null,
        );
    }

    /**
     * Архивирует ассет, не трогая файл и ссылки сцен.
     *
     * @param ArchiveAssetInput $input JSON.
     *
     * @return array<string, mixed> Пустой результат.
     *
     * @throws ActionException Если актора нет или архив запрещён.
     */
    public function archive(ArchiveAssetInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $assetRecord = $this->itemRepository->getById($input->id);
        $this->assertCanEdit($actor, $assetRecord);
        if (!$assetRecord->isArchived()) {
            $this->itemRepository->markArchived($assetRecord->getId());
        }

        return [];
    }

    /**
     * Активная коллекция по плоской паре полей.
     *
     * @param string $collectionModule Модуль-владелец.
     * @param string $collectionCode Код коллекции.
     *
     * @return AssetCollectionRecord Коллекция.
     *
     * @throws ActionException Если ключ недопустим или коллекции нет.
     */
    private function requireCollection(string $collectionModule, string $collectionCode): AssetCollectionRecord
    {
        return $this->collectionRepository->getActiveByModuleAndCode(
            $this->inputValidator->normalizeModule($collectionModule),
            $this->inputValidator->normalizeCode($collectionCode),
        );
    }

    /**
     * Проверяет право менять ассет.
     *
     * @param RequestActor $actor Актор.
     * @param AssetRecord $assetRecord Ассет.
     *
     * @return void
     *
     * @throws AssetDeniedException Если это общий ассет без bypass или чужой личный.
     */
    private function assertCanEdit(RequestActor $actor, AssetRecord $assetRecord): void
    {
        if ($assetRecord->isShared()) {
            if (!$actor->hasBypass()) {
                throw new AssetDeniedException('Only a bypass actor can change shared assets');
            }

            return;
        }

        if ($assetRecord->getOwnerId() !== $actor->getUserId()) {
            throw new AssetDeniedException();
        }
    }
}
