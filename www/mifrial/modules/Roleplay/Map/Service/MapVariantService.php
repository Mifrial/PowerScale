<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapVariantRecord;
use Mifrial\Roleplay\Map\Repository\MapVariantRepository;

/**
 * Чтение вариантов карты.
 */
final class MapVariantService
{
    /**
     * Создаёт сервис.
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
     * Вариант в границах карты.
     *
     * @param int $mapId Карта.
     * @param int $variantId Вариант.
     *
     * @return MapVariantRecord Вариант.
     */
    public function requireVariant(int $mapId, int $variantId): MapVariantRecord
    {
        return $this->variantRepository->getForMap($mapId, $variantId);
    }

    /**
     * Варианты карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapVariantRecord> Варианты.
     */
    public function findVariants(int $mapId): array
    {
        return $this->variantRepository->findByMap($mapId);
    }
}
