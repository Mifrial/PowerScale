<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Map\Repository\MapRepository;

/**
 * Запись снимка карты: строка карты, затем сущности и связки.
 */
final class MapSaveWriter
{
    /**
     * Создаёт писатель.
     *
     * @param MapRepository $mapRepository Строки карт.
     * @param MapVariantWriter $variantWriter Варианты.
     * @param MapNodeWriter $nodeWriter Узлы.
     * @param MapGraphSaveWriter $graphWriter Области.
     * @param MapLineSaveWriter $lineWriter Ленты.
     * @param MapLinkSaveWriter $linkWriter Размещения и формы.
     *
     * @return void
     */
    public function __construct(
        private readonly MapRepository $mapRepository,
        private readonly MapVariantWriter $variantWriter,
        private readonly MapNodeWriter $nodeWriter,
        private readonly MapGraphSaveWriter $graphWriter,
        private readonly MapLineSaveWriter $lineWriter,
        private readonly MapLinkSaveWriter $linkWriter,
    ) {
    }

    /**
     * Пишет снимок карты.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $mapId Карта или null для новой.
     * @param array<string, mixed> $data Нормализованные данные.
     *
     * @return int Id карты.
     */
    public function write(RequestActor $actor, ?int $mapId, array $data): int
    {
        $isNew = $mapId === null;
        $mapId = $this->writeMap($actor, $mapId, $data);
        $variantMap = $this->variantWriter->write($mapId, $data['variants'], $isNew);
        $nodeMap = $this->nodeWriter->write($mapId, $data['nodes']);
        $areaMap = $this->graphWriter->areas($mapId, $data['areas'], $nodeMap);
        $this->lineWriter->write($variantMap, $data['lines']);
        $this->linkWriter->placements($variantMap, $data['nodes'], $nodeMap);
        $this->linkWriter->shapes($variantMap, $data['areas'], $areaMap);
        $this->touchMap($mapId, $actor, $isNew);

        return $mapId;
    }

    /**
     * Создаёт или правит строку карты.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $mapId Карта.
     * @param array<string, mixed> $data Данные.
     *
     * @return int Id карты.
     */
    private function writeMap(RequestActor $actor, ?int $mapId, array $data): int
    {
        if ($mapId !== null) {
            $this->mapRepository->update($mapId, $this->mapFields($data));

            return $mapId;
        }

        return $this->mapRepository->add(
            $actor->getUserId(),
            $data['name'],
            $data['description'],
            $data['visibility'],
        );
    }

    /**
     * Поля строки карты.
     *
     * @param array<string, mixed> $data Данные.
     *
     * @return array<string, mixed> Поля.
     */
    private function mapFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'],
            'visibility' => $data['visibility'],
        ];
    }

    /**
     * Версия, время и автор публикации.
     *
     * @param int $mapId Карта.
     * @param RequestActor $actor Актор.
     * @param bool $isNew Новая карта.
     *
     * @return void
     */
    private function touchMap(int $mapId, RequestActor $actor, bool $isNew): void
    {
        $mapRecord = $this->mapRepository->getById($mapId);
        $this->mapRepository->update($mapId, [
            'version' => $isNew ? 1 : $mapRecord->getVersion() + 1,
            'updated_at' => DateTime::now(),
            'updated_by' => $actor->getUserId(),
        ]);
    }
}
