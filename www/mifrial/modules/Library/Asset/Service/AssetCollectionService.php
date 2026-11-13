<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use Mifrial\Library\Asset\Exception\AssetInvalidException;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Library\Asset\Repository\AssetCollectionRepository;

/**
 * Идемпотентная запись коллекций модулей-потребителей.
 */
final class AssetCollectionService implements IAssetCollections
{
    /**
     * Создаёт сервис.
     *
     * @param AssetCollectionRepository $collectionRepository Строки коллекций.
     * @param AssetInputValidator $inputValidator Проверка ключей.
     *
     * @return void
     */
    public function __construct(
        private readonly AssetCollectionRepository $collectionRepository,
        private readonly AssetInputValidator $inputValidator,
    ) {
    }

    /**
     * Гарантирует существование коллекции назначения.
     *
     * @param string $module Модуль-владелец в форме `Group/Module`.
     * @param string $code Код внутри модуля.
     * @param string $title Подпись для UI.
     *
     * @return void
     *
     * @throws AssetInvalidException Если ключ или подпись недопустимы.
     */
    public function ensure(string $module, string $code, string $title): void
    {
        $normalizedModule = $this->inputValidator->normalizeModule($module);
        $normalizedCode = $this->inputValidator->normalizeCode($code);
        $normalizedTitle = $this->inputValidator->normalizeCollectionTitle($title);
        if ($this->collectionRepository->findByModuleAndCode($normalizedModule, $normalizedCode) !== null) {
            return;
        }

        $this->collectionRepository->add($normalizedModule, $normalizedCode, $normalizedTitle);
    }
}
