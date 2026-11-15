<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Lore\Dto\LoreWorldDetail;
use Mifrial\Roleplay\Lore\Dto\LoreWorldPage;
use Mifrial\Roleplay\Lore\Dto\LoreWorldQuery;
use Mifrial\Roleplay\Lore\Dto\LoreSectionValues;
use Mifrial\Roleplay\Lore\Dto\LoreWorldRecord;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;
use Mifrial\Roleplay\Lore\Repository\LoreArticleRepository;
use Mifrial\Roleplay\Lore\Repository\LoreSectionRepository;
use Mifrial\Roleplay\Lore\Repository\LoreWorldRepository;

/**
 * Сценарии мира кодекса: каталог, мета, архив и дерево разделов.
 */
final class LoreWorlds
{
    /**
     * Создаёт сервис.
     *
     * @param LoreAccessService $accessService Доступ.
     * @param LoreWorldRepository $worldRepository Строки миров.
     * @param LoreSectionRepository $sectionRepository Строки разделов.
     * @param LoreArticleRepository $articleRepository Строки статей.
     * @param LoreWorldValidator $worldValidator Проверка мета.
     * @param LoreTreeValidator $treeValidator Проверка дерева.
     * @param LoreVersionGuard $versionGuard Версии и конфликты.
     * @param ITransactionRunner $transactionRunner Единица работы.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreAccessService $accessService,
        private readonly LoreWorldRepository $worldRepository,
        private readonly LoreSectionRepository $sectionRepository,
        private readonly LoreArticleRepository $articleRepository,
        private readonly LoreWorldValidator $worldValidator,
        private readonly LoreTreeValidator $treeValidator,
        private readonly LoreVersionGuard $versionGuard,
        private readonly ITransactionRunner $transactionRunner,
    ) {
    }

    /**
     * Страница миров с учётом доступа.
     *
     * @param RequestActor $actor Актор.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока.
     * @param string $state Active, archived или all.
     * @param string|null $visibility Public или private.
     *
     * @return LoreWorldPage Страница.
     *
     * @throws LoreInvalidException Если границы неверны.
     */
    public function findPage(
        RequestActor $actor,
        int $limit,
        int $offset,
        ?string $searchQuery,
        string $state,
        ?string $visibility,
    ): LoreWorldPage {
        LorePayload::assertPageBounds($limit, $offset);
        $this->assertWorldFilter($state, $visibility);
        $trimmedQuery = LorePayload::searchQuery($searchQuery);

        return $this->worldRepository->findPage(new LoreWorldQuery(
            $actor->getUserId(),
            $this->accessService->canViewAll($actor),
            $limit,
            $offset,
            $trimmedQuery,
            $state,
            $visibility,
        ));
    }

    /**
     * Мир с деревом и доступными статьями.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код.
     *
     * @return LoreWorldDetail Снимок.
     */
    public function detail(RequestActor $actor, string $worldCode): LoreWorldDetail
    {
        return $this->assembleDetail($actor, $this->accessService->requireWorldForView($actor, $worldCode));
    }

    /**
     * Создаёт или обновляет мета мира.
     *
     * @param RequestActor $actor Актор.
     * @param array<string|int, mixed> $payload Снимок.
     * @param int|null $expectedVersion Версия или null для создания.
     *
     * @return LoreWorldRecord Мир.
     *
     * @throws LoreInvalidException Если снимок или версия недопустимы.
     */
    public function saveWorld(RequestActor $actor, array $payload, ?int $expectedVersion): LoreWorldRecord
    {
        $values = $this->worldValidator->validate($payload);
        if ($expectedVersion === null) {
            $this->accessService->requireCreate();
            if ($this->worldRepository->isCodeTaken($values->getCode())) {
                throw new LoreInvalidException('Lore world code is already taken');
            }

            return $this->worldRepository->add($values, $actor->getUserId(), DateTime::now());
        }

        $worldRecord = $this->accessService->requireWorldForEdit($actor, $values->getCode());
        $this->versionGuard->assert(
            $expectedVersion,
            $worldRecord->getVersion(),
            $worldRecord->getUpdatedAt()->toUnix(),
            $worldRecord->getUpdatedBy(),
        );

        return $this->worldRepository->update($worldRecord, $values, $actor->getUserId(), DateTime::now());
    }

    /**
     * Ставит или снимает архив мира.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код.
     * @param bool $archived Архив.
     * @param int|null $expectedVersion Версия клиента.
     *
     * @return LoreWorldRecord Мир.
     *
     * @throws LoreInvalidException Если версии нет.
     */
    public function archive(
        RequestActor $actor,
        string $worldCode,
        bool $archived,
        ?int $expectedVersion,
    ): LoreWorldRecord {
        $worldRecord = $this->accessService->requireWorldForEdit($actor, $worldCode);
        $this->versionGuard->assert(
            $this->versionGuard->requireExpected($expectedVersion, 'expectedVersion'),
            $worldRecord->getVersion(),
            $worldRecord->getUpdatedAt()->toUnix(),
            $worldRecord->getUpdatedBy(),
        );

        return $this->worldRepository->archive($worldRecord, $archived, $actor->getUserId(), DateTime::now());
    }

    /**
     * Заменяет дерево разделов целиком.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код мира.
     * @param array<string|int, mixed> $sections Узлы.
     * @param int|null $expectedWorldVersion Версия клиента.
     *
     * @return LoreWorldDetail Снимок после записи.
     *
     * @throws LoreInvalidException Если дерево или версия недопустимы.
     */
    public function saveTree(
        RequestActor $actor,
        string $worldCode,
        array $sections,
        ?int $expectedWorldVersion,
    ): LoreWorldDetail {
        $worldRecord = $this->accessService->requireWorldForEdit($actor, $worldCode);
        $this->versionGuard->assert(
            $this->versionGuard->requireExpected($expectedWorldVersion, 'expectedWorldVersion'),
            $worldRecord->getVersion(),
            $worldRecord->getUpdatedAt()->toUnix(),
            $worldRecord->getUpdatedBy(),
        );
        $sectionValues = $this->treeValidator->validate($sections);
        $this->assertArticlesKeepSections($worldRecord, $sectionValues);

        $now = DateTime::now();
        $this->transactionRunner->run(function () use ($actor, $worldRecord, $sectionValues, $now): void {
            $this->sectionRepository->replaceTree($worldRecord->getId(), $sectionValues, $now);
            $this->worldRepository->touch($worldRecord, $actor->getUserId(), $now);
        });

        return $this->assembleDetail($actor, $this->worldRepository->getById($worldRecord->getId()));
    }

    /**
     * Собирает снимок мира.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     *
     * @return LoreWorldDetail Снимок.
     */
    public function assembleDetail(RequestActor $actor, LoreWorldRecord $worldRecord): LoreWorldDetail
    {
        $canViewAll = $this->accessService->canViewAll($actor);
        $sections = $this->sectionRepository->listByWorld($worldRecord->getId());
        $articles = $this->articleRepository->listForTree(
            $worldRecord->getId(),
            $canViewAll || $this->accessService->canEditWorld($actor, $worldRecord),
            $actor->getUserId(),
        );

        return new LoreWorldDetail(
            $worldRecord,
            $sections,
            $articles,
            $this->accessService->canEditWorld($actor, $worldRecord),
        );
    }

    /**
     * Проверяет состояние и видимость.
     *
     * @param string $state Состояние.
     * @param string|null $visibility Видимость.
     *
     * @return void
     *
     * @throws LoreInvalidException Если фильтр недопустим.
     */
    private function assertWorldFilter(string $state, ?string $visibility): void
    {
        if (!in_array($state, ['active', 'archived', 'all'], true)) {
            throw new LoreInvalidException('Lore world state must be active, archived or all');
        }

        if ($visibility !== null && !in_array($visibility, ['public', 'private'], true)) {
            throw new LoreInvalidException('Lore world visibility must be public or private');
        }
    }


    /**
     * Все статьи остаются в существующих разделах.
     *
     * @param LoreWorldRecord $worldRecord Мир.
     * @param array<int, LoreSectionValues> $sectionValues Новое дерево.
     *
     * @return void
     *
     * @throws LoreInvalidException Если статья осталась без раздела.
     */
    private function assertArticlesKeepSections(LoreWorldRecord $worldRecord, array $sectionValues): void
    {
        $sectionCodes = [];
        foreach ($sectionValues as $sectionValue) {
            $sectionCodes[$sectionValue->getCode()] = true;
        }

        foreach ($this->articleRepository->listByWorld($worldRecord->getId()) as $articleRecord) {
            $sectionCode = $articleRecord->getSectionCode();
            if ($sectionCode !== null && !isset($sectionCodes[$sectionCode])) {
                throw new LoreInvalidException(
                    'Lore article ' . $articleRecord->getCode() . ' keeps a removed section ' . $sectionCode,
                );
            }
        }
    }

}
