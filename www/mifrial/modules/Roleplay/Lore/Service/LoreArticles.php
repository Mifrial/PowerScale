<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong,MifrialCodingStandard.Metrics.MethodQuality.CyclomaticComplexityTooHigh -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Lore\Dto\LoreArticleDetail;
use Mifrial\Roleplay\Lore\Dto\LoreArticleLink;
use Mifrial\Roleplay\Lore\Dto\LoreArticlePage;
use Mifrial\Roleplay\Lore\Dto\LoreArticleQuery;
use Mifrial\Roleplay\Lore\Dto\LoreArticleRecord;
use Mifrial\Roleplay\Lore\Dto\LoreArticleValues;
use Mifrial\Roleplay\Lore\Dto\LoreBacklink;
use Mifrial\Roleplay\Lore\Dto\LoreLinkValues;
use Mifrial\Roleplay\Lore\Dto\LoreReader;
use Mifrial\Roleplay\Lore\Dto\LoreWorldRecord;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;
use Mifrial\Roleplay\Lore\Exception\LoreNotFoundException;
use Mifrial\Roleplay\Lore\Repository\LoreArticleRepository;
use Mifrial\Roleplay\Lore\Repository\LoreLinkRepository;
use Mifrial\Roleplay\Lore\Repository\LoreSectionRepository;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Сценарии статьи кодекса: поиск, чтение, запись, архив и читатели.
 */
final class LoreArticles
{
    /**
     * Создаёт сервис.
     *
     * @param LoreAccessService $accessService Доступ.
     * @param LoreSectionRepository $sectionRepository Строки разделов.
     * @param LoreArticleRepository $articleRepository Строки статей.
     * @param LoreLinkRepository $linkRepository Строки ссылок.
     * @param LoreArticleValidator $articleValidator Проверка снимка.
     * @param LoreLinkExtractor $linkExtractor Извлечение ссылок.
     * @param LoreVersionGuard $versionGuard Версии и конфликты.
     * @param ITransactionRunner $transactionRunner Единица работы.
     * @param IUserAccounts $userAccounts Учётки читателей.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreAccessService $accessService,
        private readonly LoreSectionRepository $sectionRepository,
        private readonly LoreArticleRepository $articleRepository,
        private readonly LoreLinkRepository $linkRepository,
        private readonly LoreArticleValidator $articleValidator,
        private readonly LoreLinkExtractor $linkExtractor,
        private readonly LoreVersionGuard $versionGuard,
        private readonly ITransactionRunner $transactionRunner,
        private readonly IUserAccounts $userAccounts,
    ) {
    }

    /**
     * Страница доступных статей мира.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код мира.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $searchQuery Подстрока.
     * @param string|null $sectionCode Раздел.
     * @param array<int, int> $keywordIds Признаки.
     * @param string|null $status Статус.
     * @param string|null $visibility Видимость.
     * @param string $sort Order или recent.
     * @param bool $includeArchived Показывать архивные.
     *
     * @return LoreArticlePage Страница.
     *
     * @throws LoreInvalidException Если фильтры неверны.
     */
    public function findPage(
        RequestActor $actor,
        string $worldCode,
        int $limit,
        int $offset,
        ?string $searchQuery,
        ?string $sectionCode,
        array $keywordIds,
        ?string $status,
        ?string $visibility,
        string $sort,
        bool $includeArchived,
    ): LoreArticlePage {
        $this->assertQuery($limit, $offset, $status, $visibility, $sort);
        $worldRecord = $this->accessService->requireWorldForView($actor, $worldCode);
        $trimmedQuery = LorePayload::searchQuery($searchQuery);
        $trimmedSection = $sectionCode === null ? null : trim($sectionCode);

        return $this->articleRepository->findPage(new LoreArticleQuery(
            $worldRecord->getId(),
            $actor->getUserId(),
            $this->accessService->canViewAll($actor) || $this->accessService->canEditWorld($actor, $worldRecord),
            $limit,
            $offset,
            $trimmedQuery,
            $trimmedSection === '' ? null : $trimmedSection,
            $keywordIds,
            $status,
            $visibility,
            $sort,
            $includeArchived,
        ));
    }

    /**
     * Статья с исходящими ссылками, ссылками сюда и читателями.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код мира.
     * @param string $articleCode Код статьи.
     *
     * @return LoreArticleDetail Снимок.
     */
    public function detail(RequestActor $actor, string $worldCode, string $articleCode): LoreArticleDetail
    {
        $worldRecord = $this->accessService->requireWorldForView($actor, $worldCode);
        $articleRecord = $this->accessService->requireArticleForView($actor, $worldRecord, $articleCode);

        return $this->assembleDetail($actor, $worldRecord, $articleRecord);
    }

    /**
     * Создаёт или обновляет статью.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код мира.
     * @param array<string|int, mixed> $payload Снимок.
     * @param int|null $expectedVersion Версия или null для создания.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreInvalidException Если снимок недопустим.
     */
    public function save(
        RequestActor $actor,
        string $worldCode,
        array $payload,
        ?int $expectedVersion,
    ): LoreArticleRecord {
        $worldRecord = $this->accessService->requireWorldForEdit($actor, $worldCode);
        $values = $this->articleValidator->validate($payload);
        $this->assertSectionExists($worldRecord, $values->getSectionCode());
        $existingRecord = $this->articleRepository->findByCode($worldRecord->getId(), $values->getCode());
        $links = $this->linkExtractor->extract($values->getBodyHtml());

        return $expectedVersion === null
            ? $this->create($actor, $worldRecord, $values, $links, $existingRecord)
            : $this->replace($actor, $worldRecord, $values, $links, $existingRecord, $expectedVersion);
    }

    /**
     * Ставит или снимает архив статьи.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код мира.
     * @param string $articleCode Код статьи.
     * @param bool $archived Архив.
     * @param int|null $expectedVersion Версия клиента.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreInvalidException Если версии нет.
     */
    public function archive(
        RequestActor $actor,
        string $worldCode,
        string $articleCode,
        bool $archived,
        ?int $expectedVersion,
    ): LoreArticleRecord {
        $worldRecord = $this->accessService->requireWorldForEdit($actor, $worldCode);
        $articleRecord = $this->accessService->requireArticleForEdit($actor, $worldRecord, $articleCode);
        $this->versionGuard->assert(
            $this->versionGuard->requireExpected($expectedVersion, 'expectedVersion'),
            $articleRecord->getVersion(),
            $articleRecord->getUpdatedAt()->toUnix(),
            $articleRecord->getUpdatedBy(),
        );

        return $this->articleRepository->archive(
            $articleRecord,
            $archived,
            $actor->getUserId(),
            DateTime::now(),
        );
    }

    /**
     * Поиск пользователей для списка читателей.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код мира.
     * @param string $searchQuery Подстрока.
     * @param int $limit Размер 1..50.
     *
     * @return array<int, LoreReader> Кандидаты.
     *
     * @throws LoreInvalidException Если границы неверны.
     */
    public function readerCandidates(
        RequestActor $actor,
        string $worldCode,
        string $searchQuery,
        int $limit,
    ): array {
        $this->accessService->requireWorldForEdit($actor, $worldCode);
        if ($limit < 1 || $limit > 50) {
            throw new LoreInvalidException('Lore reader candidates limit must be 1..50');
        }

        $trimmedQuery = trim($searchQuery);
        if (mb_strlen($trimmedQuery, 'UTF-8') > 100) {
            throw new LoreInvalidException('Lore search query is too long');
        }

        $userPage = $this->userAccounts->findPage(
            $limit,
            0,
            $trimmedQuery === '' ? null : $trimmedQuery,
            true,
        );
        $readers = [];
        foreach ($userPage->getRecords() as $userRecord) {
            $readers[] = new LoreReader($userRecord->getId(), $userRecord->getLogin(), $userRecord->getName());
        }

        return $readers;
    }

    /**
     * Создаёт статью.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param LoreArticleValues $values Поля.
     * @param array<int, LoreLinkValues> $links Ссылки.
     * @param LoreArticleRecord|null $existingRecord Занятый код.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreInvalidException Если код занят.
     */
    private function create(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        LoreArticleValues $values,
        array $links,
        ?LoreArticleRecord $existingRecord,
    ): LoreArticleRecord {
        if ($existingRecord !== null) {
            throw new LoreInvalidException('Lore article code is already taken');
        }

        $now = DateTime::now();

        return $this->transactionRunner->run(function () use (
            $actor,
            $worldRecord,
            $values,
            $links,
            $now,
        ): LoreArticleRecord {
            $articleRecord = $this->articleRepository->add(
                $worldRecord->getId(),
                $values,
                $actor->getUserId(),
                $now,
            );
            $this->linkRepository->replaceForArticle($worldRecord->getId(), $articleRecord->getId(), $links);

            return $articleRecord;
        });
    }

    /**
     * Заменяет статью с проверкой версии.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param LoreArticleValues $values Поля.
     * @param array<int, LoreLinkValues> $links Ссылки.
     * @param LoreArticleRecord|null $existingRecord Текущая статья.
     * @param int $expectedVersion Версия клиента.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreNotFoundException Если статьи нет.
     */
    private function replace(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        LoreArticleValues $values,
        array $links,
        ?LoreArticleRecord $existingRecord,
        int $expectedVersion,
    ): LoreArticleRecord {
        if ($existingRecord === null) {
            throw new LoreNotFoundException('Lore article was not found');
        }

        $this->versionGuard->assert(
            $expectedVersion,
            $existingRecord->getVersion(),
            $existingRecord->getUpdatedAt()->toUnix(),
            $existingRecord->getUpdatedBy(),
        );
        $now = DateTime::now();

        return $this->transactionRunner->run(function () use (
            $actor,
            $worldRecord,
            $existingRecord,
            $values,
            $links,
            $now,
        ): LoreArticleRecord {
            $articleRecord = $this->articleRepository->update(
                $existingRecord,
                $values,
                $actor->getUserId(),
                $now,
            );
            $this->linkRepository->replaceForArticle($worldRecord->getId(), $articleRecord->getId(), $links);

            return $articleRecord;
        });
    }

    /**
     * Проверяет границы и перечисления фильтра.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param string|null $status Статус.
     * @param string|null $visibility Видимость.
     * @param string $sort Сортировка.
     *
     * @return void
     *
     * @throws LoreInvalidException Если фильтр недопустим.
     */
    private function assertQuery(
        int $limit,
        int $offset,
        ?string $status,
        ?string $visibility,
        string $sort,
    ): void {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new LoreInvalidException('Lore page bounds are invalid');
        }

        if (!in_array($sort, ['order', 'recent'], true)) {
            throw new LoreInvalidException('Lore article sort must be order or recent');
        }

        if ($status !== null && !in_array($status, ['draft', 'published'], true)) {
            throw new LoreInvalidException('Lore article status must be draft or published');
        }

        if ($visibility !== null && !in_array($visibility, ['public', 'secret'], true)) {
            throw new LoreInvalidException('Lore article visibility must be public or secret');
        }
    }


    /**
     * Собирает снимок статьи.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param LoreArticleRecord $articleRecord Статья.
     *
     * @return LoreArticleDetail Снимок.
     */
    private function assembleDetail(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        LoreArticleRecord $articleRecord,
    ): LoreArticleDetail {
        $canEdit = $this->accessService->canEditWorld($actor, $worldRecord);
        $links = [];
        foreach ($this->linkRepository->listOutgoing($articleRecord->getId()) as $linkValues) {
            $links[] = new LoreArticleLink($linkValues->getType(), $linkValues->getCode());
        }

        return new LoreArticleDetail(
            $articleRecord,
            $canEdit,
            $links,
            $this->backlinks($actor, $worldRecord, $articleRecord),
            $canEdit ? $this->readers($articleRecord) : [],
        );
    }

    /**
     * Доступные зрителю ссылки сюда.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param LoreArticleRecord $articleRecord Статья.
     *
     * @return array<int, LoreBacklink> Ссылки.
     */
    private function backlinks(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        LoreArticleRecord $articleRecord,
    ): array {
        $sourceIds = $this->linkRepository->sourceIdsByTarget(
            $worldRecord->getId(),
            'lore',
            $articleRecord->getCode(),
        );
        $backlinks = [];
        foreach ($this->articleRepository->listAccessRows($sourceIds) as $row) {
            if ($row['id'] === $articleRecord->getId()) {
                continue;
            }

            if (
                !$this->accessService->canViewArticleState(
                    $actor,
                    $worldRecord,
                    $row['status'],
                    $row['visibility'],
                    $row['readerIds'],
                )
            ) {
                continue;
            }

            $backlinks[] = new LoreBacklink($row['id'], $row['code'], $row['title']);
        }

        return $backlinks;
    }

    /**
     * Читатели статьи с логинами.
     *
     * @param LoreArticleRecord $articleRecord Статья.
     *
     * @return array<int, LoreReader> Читатели.
     */
    private function readers(LoreArticleRecord $articleRecord): array
    {
        $readerIds = $articleRecord->getReaderIds();
        if ($readerIds === []) {
            return [];
        }

        $readers = [];
        foreach ($this->userAccounts->getByIds($readerIds) as $userRecord) {
            $readers[] = new LoreReader($userRecord->getId(), $userRecord->getLogin(), $userRecord->getName());
        }

        return $readers;
    }

    /**
     * Раздел статьи существует в дереве мира.
     *
     * @param LoreWorldRecord $worldRecord Мир.
     * @param string|null $sectionCode Код раздела.
     *
     * @return void
     *
     * @throws LoreInvalidException Если раздела нет.
     */
    private function assertSectionExists(LoreWorldRecord $worldRecord, ?string $sectionCode): void
    {
        if ($sectionCode === null) {
            return;
        }

        if (!in_array($sectionCode, $this->sectionRepository->codesByWorld($worldRecord->getId()), true)) {
            throw new LoreInvalidException('Lore section ' . $sectionCode . ' was not found');
        }
    }
}
