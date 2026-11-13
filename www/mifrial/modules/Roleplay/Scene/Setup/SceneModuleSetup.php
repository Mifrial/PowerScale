<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Roleplay\Scene\Schema\SceneSchema;

/**
 * Таблицы локаций сцен и коллекции ассетов для CLI setup.
 */
final class SceneModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup.
     *
     * @param IAssetCollections $assetCollections Порт коллекций.
     *
     * @return void
     */
    public function __construct(
        private readonly IAssetCollections $assetCollections,
    ) {
    }

    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return SceneSchema::getTableClasses();
    }

    /**
     * Коллекции декора и террейна сцены.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return [
            new SeedSceneAssetCollectionsStep($this->assetCollections),
        ];
    }
}
