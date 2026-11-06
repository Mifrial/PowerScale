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
     *
     * @return void
     */
    public function __construct(
        private readonly MapVariantRepository $variantRepository,
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
        [$updates, $inserts] = $this->split($variants, $this->index($existing));
        foreach ($updates as $id => $variant) {
            $this->variantRepository->update($id, $this->fields($variant));
        }

        $this->deleteMissing($existing, $updates);

        $idMap = $this->ids($updates);
        foreach ($inserts as $variant) {
            $idMap[$variant['id']] = $this->insert($mapId, $variant);
        }

        return $idMap;
    }

    /**
     * Делит снимок на обновления и вставки.
     *
     * @param array<int, array<string, mixed>> $variants Варианты.
     * @param array<int, true> $existingById Известные id.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} Части.
     *
     * @throws MapInvalidException Если вариант снимка неизвестен.
     */
    private function split(array $variants, array $existingById): array
    {
        $updates = [];
        $inserts = [];
        foreach ($variants as $variant) {
            $id = $variant['id'];
            if ($id < 0) {
                $inserts[] = $variant;

                continue;
            }

            if (!isset($existingById[$id])) {
                throw new MapInvalidException('Map variant is unknown');
            }

            $updates[$id] = $variant;
        }

        return [$updates, $inserts];
    }

    /**
     * Удаляет варианты, которых нет в снимке.
     *
     * @param array<int, MapVariantRecord> $existing Существующие.
     * @param array<int, array<string, mixed>> $updates Обновляемые.
     *
     * @return void
     */
    private function deleteMissing(array $existing, array $updates): void
    {
        foreach ($existing as $variantRecord) {
            if (!isset($updates[$variantRecord->getId()])) {
                $this->variantRepository->delete($variantRecord->getId());
            }
        }
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
     * Карта id для обновлённых строк.
     *
     * @param array<int, array<string, mixed>> $updates Обновляемые.
     *
     * @return array<int, int> Id снимка → реальный id.
     */
    private function ids(array $updates): array
    {
        $idMap = [];
        foreach ($updates as $id => $variant) {
            $idMap[$id] = $id;
        }

        return $idMap;
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
