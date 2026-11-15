<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Roleplay\Lore\Dto\Action\ArchiveLoreArticleInput;
use Mifrial\Roleplay\Lore\Dto\Action\FindLoreArticlePageInput;
use Mifrial\Roleplay\Lore\Dto\Action\FindLoreReaderCandidatesInput;
use Mifrial\Roleplay\Lore\Dto\Action\GetLoreArticleInput;
use Mifrial\Roleplay\Lore\Dto\Action\SaveLoreArticleInput;

/**
 * HTTP-сценарии статей кодекса.
 */
final class LoreArticleHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param LoreAccessService $accessService Доступ.
     * @param LoreArticles $articles Сценарии статьи.
     * @param LoreViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreAccessService $accessService,
        private readonly LoreArticles $articles,
        private readonly LoreViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Страница статей.
     *
     * @param FindLoreArticlePageInput $input JSON.
     *
     * @return array<string, mixed> Страница.
     */
    public function findPage(FindLoreArticlePageInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $page = $this->articles->findPage(
            $actor,
            $input->worldCode,
            $input->limit,
            $input->offset,
            $input->q->isPresent() ? $input->q->getValue() : null,
            $input->sectionCode->isPresent() ? $input->sectionCode->getValue() : null,
            $this->keywordIds($input),
            $input->status->isPresent() ? $input->status->getValue() : null,
            $input->visibility->isPresent() ? $input->visibility->getValue() : null,
            $input->sort->isPresent() ? $input->sort->getValue() : 'order',
            $input->includeArchived,
        );
        $items = [];
        foreach ($page->getItems() as $articleSummary) {
            $items[] = $this->viewAssembler->assembleArticleSummary($articleSummary);
        }

        return ['items' => $items, 'total' => $page->getTotal()];
    }

    /**
     * Статья с ссылками.
     *
     * @param GetLoreArticleInput $input JSON.
     *
     * @return array<string, mixed> Статья.
     */
    public function get(GetLoreArticleInput $input): array
    {
        $actor = $this->accessService->requireActor();

        return $this->viewAssembler->assembleArticleDetail(
            $this->articles->detail($actor, $input->worldCode, $input->articleCode),
        );
    }

    /**
     * Создаёт или обновляет статью.
     *
     * @param SaveLoreArticleInput $input JSON.
     *
     * @return array<string, mixed> Статья.
     */
    public function save(SaveLoreArticleInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $articleRecord = $this->articles->save(
            $actor,
            $input->worldCode,
            $input->article,
            $input->expectedVersion->isPresent() ? $input->expectedVersion->getValue() : null,
        );

        return $this->viewAssembler->assembleArticleDetail(
            $this->articles->detail($actor, $input->worldCode, $articleRecord->getCode()),
        );
    }

    /**
     * Архивирует статью.
     *
     * @param ArchiveLoreArticleInput $input JSON.
     *
     * @return array<string, mixed> Статья.
     */
    public function archive(ArchiveLoreArticleInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $articleRecord = $this->articles->archive(
            $actor,
            $input->worldCode,
            $input->articleCode,
            $input->archived,
            $input->expectedVersion->isPresent() ? $input->expectedVersion->getValue() : null,
        );

        return $this->viewAssembler->assembleArticle($articleRecord);
    }

    /**
     * Кандидаты в читатели.
     *
     * @param FindLoreReaderCandidatesInput $input JSON.
     *
     * @return array<string, mixed> Кандидаты.
     */
    public function findReaderCandidates(FindLoreReaderCandidatesInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $readers = $this->articles->readerCandidates(
            $actor,
            $input->worldCode,
            $input->q->isPresent() ? $input->q->getValue() : '',
            $input->limit,
        );
        $items = [];
        foreach ($readers as $reader) {
            $items[] = ['id' => $reader->getId(), 'login' => $reader->getLogin(), 'name' => $reader->getName()];
        }

        return ['items' => $items];
    }

    /**
     * Целые id признаков из входа.
     *
     * @param FindLoreArticlePageInput $input JSON.
     *
     * @return array<int, int> Id.
     */
    private function keywordIds(FindLoreArticlePageInput $input): array
    {
        $keywordIds = [];
        foreach ($input->keywordIds as $keywordId) {
            if (is_int($keywordId) && $keywordId > 0) {
                $keywordIds[$keywordId] = $keywordId;
            }
        }

        return array_values($keywordIds);
    }
}
