<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Setup;

use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Library\Asset\Exception\AssetInvalidException;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Roleplay\Scene\Value\SceneAssetCollections;

/**
 * Заводит коллекции декора и террейна сцены в библиотеке ассетов.
 */
final class SeedSceneAssetCollectionsStep implements ISetupStep
{
    /**
     * Создаёт шаг.
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
     * Возвращает стабильный id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Roleplay/Scene:seed.asset-collections';
    }

    /**
     * Создаёт коллекции, если их ещё нет.
     *
     * @return void
     *
     * @throws AssetInvalidException Если код или подпись коллекции недопустимы.
     */
    public function run(): void
    {
        foreach (SceneAssetCollections::getTitles() as $code => $title) {
            $this->assetCollections->ensure('Roleplay/Scene', $code, $title);
        }
    }
}
