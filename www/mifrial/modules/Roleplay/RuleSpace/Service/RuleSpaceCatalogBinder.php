<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceCatalogRepository;

/**
 * Шаринг или insert снимка и указатель ревизии.
 */
final class RuleSpaceCatalogBinder
{
    /**
     * Создаёт биндер.
     *
     * @param RuleSpaceCatalogRepository $catalogRepository Хранение.
     *
     * @return void
     */
    public function __construct(
        private readonly RuleSpaceCatalogRepository $catalogRepository,
    ) {
    }

    /**
     * Клеит указатель после insert ревизии часов.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер часов.
     * @param RuleSpaceCatalog|null $catalog Явный снимок или шаринг latest.
     * @param array<string, true> $ruleCodes Состав ревизии.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если размещение вне состава.
     */
    public function bindAfterCommit(
        int $spaceId,
        int $revision,
        ?RuleSpaceCatalog $catalog,
        array $ruleCodes,
    ): void {
        if ($catalog instanceof RuleSpaceCatalog) {
            $this->assertPlacementCodes($catalog, $ruleCodes);
            $this->bindExplicit($spaceId, $revision, $catalog);

            return;
        }

        $this->bindSharedOrEmpty($spaceId, $revision);
    }

    /**
     * Копия снимка родителя в ребёнка.
     *
     * @param int $sourceSpaceId Родитель.
     * @param int $sourceRevision Ревизия родителя.
     * @param int $targetSpaceId Ребёнок.
     * @param int $targetRevision Ревизия ребёнка.
     *
     * @return void
     */
    public function copyInherited(
        int $sourceSpaceId,
        int $sourceRevision,
        int $targetSpaceId,
        int $targetRevision,
    ): void {
        $sourceCatalog = $this->catalogRepository->getByRevision($sourceSpaceId, $sourceRevision);
        $this->catalogRepository->insertSnapshot($targetSpaceId, 1, $sourceCatalog);
        $this->catalogRepository->insertPointer($targetSpaceId, $targetRevision, 1);
    }

    /**
     * Читает снимок ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return RuleSpaceCatalog Снимок.
     */
    public function getCatalog(int $spaceId, int $revision): RuleSpaceCatalog
    {
        return $this->catalogRepository->getByRevision($spaceId, $revision);
    }

    /**
     * Latest-снимок для сравнения.
     *
     * @param int $spaceId Мир.
     *
     * @return RuleSpaceCatalog|null Нет указателей.
     */
    public function findLatestCatalog(int $spaceId): ?RuleSpaceCatalog
    {
        $sectionVersion = $this->catalogRepository->findLatestSectionVersion($spaceId);
        if ($sectionVersion === null) {
            return null;
        }

        return $this->catalogRepository->getBySectionVersion($spaceId, $sectionVersion);
    }

    /**
     * Явный DTO: шаринг или новый номер.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер часов.
     * @param RuleSpaceCatalog $catalog Снимок.
     *
     * @return void
     */
    private function bindExplicit(int $spaceId, int $revision, RuleSpaceCatalog $catalog): void
    {
        $latestVersion = $this->catalogRepository->findLatestSectionVersion($spaceId);
        if ($latestVersion !== null) {
            $latestCatalog = $this->catalogRepository->getBySectionVersion($spaceId, $latestVersion);
            if ($latestCatalog->fingerprint() === $catalog->fingerprint()) {
                $this->catalogRepository->insertPointer($spaceId, $revision, $latestVersion);

                return;
            }
        }

        $sectionVersion = $this->catalogRepository->nextSectionVersion($spaceId);
        $this->catalogRepository->insertSnapshot($spaceId, $sectionVersion, $catalog);
        $this->catalogRepository->insertPointer($spaceId, $revision, $sectionVersion);
    }

    /**
     * Нет ключа sections: latest или пустой снимок 1.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return void
     */
    private function bindSharedOrEmpty(int $spaceId, int $revision): void
    {
        $latestVersion = $this->catalogRepository->findLatestSectionVersion($spaceId);
        if ($latestVersion !== null) {
            $this->catalogRepository->insertPointer($spaceId, $revision, $latestVersion);

            return;
        }

        $this->catalogRepository->insertPointer($spaceId, $revision, 1);
    }

    /**
     * rule_code размещения есть в составе.
     *
     * @param RuleSpaceCatalog $catalog Снимок.
     * @param array<string, true> $ruleCodes Состав.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если кода нет.
     */
    private function assertPlacementCodes(RuleSpaceCatalog $catalog, array $ruleCodes): void
    {
        foreach ($catalog->getPlacements() as $placement) {
            if (!isset($ruleCodes[$placement->getRuleCode()])) {
                throw new RuleSpaceInvalidException('Catalog rule is not in composition');
            }
        }
    }
}
