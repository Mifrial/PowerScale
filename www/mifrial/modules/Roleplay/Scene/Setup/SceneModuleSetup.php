<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Roleplay\Scene\Repository\SceneBoardRepository;
use Mifrial\Roleplay\Scene\Repository\SceneEnclosureRepository;
use Mifrial\Roleplay\Scene\Repository\SceneLocationRepository;
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
     * @param SceneLocationRepository $locations Локации.
     * @param SceneBoardRepository $boards Поля.
     * @param SceneEnclosureRepository $enclosures Вложения.
     *
     * @return void
     */
    public function __construct(
        private readonly IAssetCollections $assetCollections,
        private readonly SceneLocationRepository $locations,
        private readonly SceneBoardRepository $boards,
        private readonly SceneEnclosureRepository $enclosures,
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
            new BackfillSceneEnclosureElevationStep($this->locations, $this->boards, $this->enclosures),
        ];
    }
}
