<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapVariantRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Repository\MapVariantRepository;

/**
 * Запись вариантов: обновление, удаление и вставка.
 */
final class MapVariantWriter
{
    /**
     * Создаёт писатель.
     *
     * @param MapVariantRepository $variantRepository Строки вариантов.
     * @param MapSaveRows $rows Общие операции над строками снимка.
     *
     * @return void
     */
    public function __construct(
        private readonly MapVariantRepository $variantRepository,
        private readonly MapSaveRows $rows,
    ) {
    }

    /**
     * Пишет варианты карты.
     *
     * @param int $mapId Карта.
     * @param array<int, array<string, mixed>> $variants Варианты снимка.
     * @param bool $isNew Новая карта.
     *
     * @return array<int, int> Id снимка → реальный id.
     *
     * @throws MapInvalidException Если вариант снимка неизвестен.
     */
    public function write(int $mapId, array $variants, bool $isNew): array
    {
        $existing = $isNew ? [] : $this->variantRepository->findByMap($mapId);
        $existingById = $this->index($existing);
        [$updates, $inserts] = $this->rows->split($variants, $existingById, 'Map variant is unknown');
        foreach ($updates as $id => $variant) {
            $this->variantRepository->update($id, $this->fields($variant));
        }

        foreach ($this->rows->stale($existingById, $updates) as $id) {
            $this->variantRepository->delete($id);
        }

        $idMap = $this->rows->ids($updates);
        foreach ($inserts as $variant) {
            $idMap[$variant['id']] = $this->insert($mapId, $variant);
        }

        return $idMap;
    }

    /**
     * Существующие id.
     *
     * @param array<int, MapVariantRecord> $variants Варианты.
     *
     * @return array<int, true> Id → true.
     */
    private function index(array $variants): array
    {
        $ids = [];
        foreach ($variants as $variantRecord) {
            $ids[$variantRecord->getId()] = true;
        }

        return $ids;
    }

    /**
     * Поля строки.
     *
     * @param array<string, mixed> $variant Вариант.
     *
     * @return array<string, mixed> Поля.
     */
    private function fields(array $variant): array
    {
        return [
            'label' => $variant['label'],
            'from_minutes' => $variant['fromMinutes'],
            'sort_order' => $variant['sortOrder'],
            'base_file_id' => $variant['baseFileId'],
            'width' => $variant['width'],
            'height' => $variant['height'],
            'millimeters_per_pixel' => $variant['millimetersPerPixel'],
            'is_default' => $variant['isDefault'],
        ];
    }

    /**
     * Вставляет вариант.
     *
     * @param int $mapId Карта.
     * @param array<string, mixed> $variant Вариант.
     *
     * @return int Реальный id.
     */
    private function insert(int $mapId, array $variant): int
    {
        return $this->variantRepository->add(
            $mapId,
            $variant['label'],
            $variant['fromMinutes'],
            $variant['sortOrder'],
            $variant['baseFileId'],
            $variant['width'],
            $variant['height'],
            $variant['millimetersPerPixel'],
            $variant['isDefault'],
        );
    }
}
