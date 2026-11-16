<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Setup;

use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Exception\SceneNotFoundException;
use Mifrial\Roleplay\Scene\Repository\SceneBoardRepository;
use Mifrial\Roleplay\Scene\Repository\SceneEnclosureRepository;
use Mifrial\Roleplay\Scene\Repository\SceneLocationRepository;

/**
 * Выставляет существующим вложениям высоту этажа по умолчанию.
 */
final class BackfillSceneEnclosureElevationStep implements ISetupStep
{
    /**
     * Высота одного этажа по умолчанию, ипари.
     */
    private const STOREY_IPARI = 2;

    /**
     * Создаёт шаг.
     *
     * @param SceneLocationRepository $locations Локации.
     * @param SceneBoardRepository $boards Поля.
     * @param SceneEnclosureRepository $enclosures Вложения.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneLocationRepository $locations,
        private readonly SceneBoardRepository $boards,
        private readonly SceneEnclosureRepository $enclosures,
    ) {
    }

    /**
     * Возвращает стабильный id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Roleplay/Scene:backfill.enclosure-elevation';
    }

    /**
     * Пишет высоту этажа вложениям, у которых она ещё нулевая.
     *
     * @return void
     *
     * @throws SceneInvalidException Если строка или выборка недопустимы.
     * @throws SceneNotFoundException Если строки вложения нет.
     */
    public function run(): void
    {
        foreach ($this->locations->findAll() as $location) {
            $boardIds = [];
            foreach ($this->boards->findByLocation($location->getId()) as $board) {
                $boardIds[] = $board->getId();
            }

            foreach ($this->enclosures->findByParentBoardIds($boardIds) as $enclosure) {
                if ($enclosure->getElevation() !== 0) {
                    continue;
                }

                $this->enclosures->update(
                    $enclosure->getId(),
                    ['elevation' => $location->getResolution() * self::STOREY_IPARI],
                );
            }
        }
    }
}
