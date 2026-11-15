<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.TooManyReturns -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Lore\Dto\LoreArticleRecord;
use Mifrial\Roleplay\Lore\Dto\LoreWorldRecord;
use Mifrial\Roleplay\Lore\Exception\LoreDeniedException;
use Mifrial\Roleplay\Lore\Exception\LoreNotFoundException;
use Mifrial\Roleplay\Lore\Repository\LoreArticleRepository;
use Mifrial\Roleplay\Lore\Repository\LoreWorldRepository;

/**
 * Доступ к мирам и статьям кодекса: владелец, админы, readers.
 */
final class LoreAccessService
{
    /**
     * Создаёт guard.
     *
     * @param IUserAccess $userAccess Guard учёток.
     * @param LoreWorldRepository $worldRepository Строки миров.
     * @param LoreArticleRepository $articleRepository Строки статей.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly LoreWorldRepository $worldRepository,
        private readonly LoreArticleRepository $articleRepository,
    ) {
    }

    /**
     * Требует актора.
     *
     * @return RequestActor Актор.
     */
    public function requireActor(): RequestActor
    {
        return $this->userAccess->requireActor();
    }

    /**
     * Требует право создавать миры.
     *
     * @return RequestActor Актор.
     */
    public function requireCreate(): RequestActor
    {
        return $this->userAccess->requireKey(LorePermissionKeys::CREATE);
    }

    /**
     * Видит все миры и статьи.
     *
     * @param RequestActor $actor Актор.
     *
     * @return bool Флаг.
     */
    public function canViewAll(RequestActor $actor): bool
    {
        return $actor->hasKey(LorePermissionKeys::VIEW_ALL)
            || $actor->hasKey(LorePermissionKeys::EDIT_ALL);
    }

    /**
     * Правит все миры и статьи.
     *
     * @param RequestActor $actor Актор.
     *
     * @return bool Флаг.
     */
    public function canEditAll(RequestActor $actor): bool
    {
        return $actor->hasKey(LorePermissionKeys::EDIT_ALL);
    }

    /**
     * Может смотреть мир.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     *
     * @return bool Флаг.
     */
    public function canViewWorld(RequestActor $actor, LoreWorldRecord $worldRecord): bool
    {
        return $this->canViewAll($actor)
            || $worldRecord->getOwnerId() === $actor->getUserId()
            || $worldRecord->getVisibility() === 'public';
    }

    /**
     * Может править мир.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     *
     * @return bool Флаг.
     */
    public function canEditWorld(RequestActor $actor, LoreWorldRecord $worldRecord): bool
    {
        return $this->canEditAll($actor) || $worldRecord->getOwnerId() === $actor->getUserId();
    }

    /**
     * Может читать статью доступного мира.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param LoreArticleRecord $articleRecord Статья.
     *
     * @return bool Флаг.
     */
    public function canViewArticle(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        LoreArticleRecord $articleRecord,
    ): bool {
        return $this->canViewArticleState(
            $actor,
            $worldRecord,
            $articleRecord->getStatus(),
            $articleRecord->getVisibility(),
            $articleRecord->getReaderIds(),
        );
    }

    /**
     * Может читать статью по её состоянию.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param string $status Draft или published.
     * @param string $visibility Public или secret.
     * @param array<int, int> $readerIds Читатели secret.
     *
     * @return bool Флаг.
     */
    public function canViewArticleState(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        string $status,
        string $visibility,
        array $readerIds,
    ): bool {
        if (!$this->canViewWorld($actor, $worldRecord)) {
            return false;
        }

        if ($this->canEditWorld($actor, $worldRecord) || $this->canViewAll($actor)) {
            return true;
        }

        if ($status !== 'published') {
            return false;
        }

        if ($visibility === 'public') {
            return true;
        }

        return in_array($actor->getUserId(), $readerIds, true);
    }

    /**
     * Мир для просмотра или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код.
     *
     * @return LoreWorldRecord Мир.
     *
     * @throws LoreNotFoundException Если мира нет или он закрыт.
     */
    public function requireWorldForView(RequestActor $actor, string $worldCode): LoreWorldRecord
    {
        $worldRecord = $this->worldRepository->getByCode($worldCode);
        if (!$this->canViewWorld($actor, $worldRecord)) {
            throw new LoreNotFoundException('Lore world was not found');
        }

        return $worldRecord;
    }

    /**
     * Мир для правки или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код.
     *
     * @return LoreWorldRecord Мир.
     *
     * @throws LoreNotFoundException Если мира нет.
     * @throws LoreDeniedException Если правки нет.
     */
    public function requireWorldForEdit(RequestActor $actor, string $worldCode): LoreWorldRecord
    {
        $worldRecord = $this->worldRepository->getByCode($worldCode);
        if (!$this->canEditWorld($actor, $worldRecord)) {
            throw new LoreDeniedException('Lore world editing is denied');
        }

        return $worldRecord;
    }

    /**
     * Статья для просмотра или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param string $articleCode Код.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreNotFoundException Если статьи нет или она закрыта.
     */
    public function requireArticleForView(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        string $articleCode,
    ): LoreArticleRecord {
        $articleRecord = $this->articleRepository->getByCode($worldRecord->getId(), $articleCode);
        if (!$this->canViewArticle($actor, $worldRecord, $articleRecord)) {
            throw new LoreNotFoundException('Lore article was not found');
        }

        return $articleRecord;
    }

    /**
     * Статья для правки или отказ.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param string $articleCode Код.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreNotFoundException Если статьи нет.
     * @throws LoreDeniedException Если правки нет.
     */
    public function requireArticleForEdit(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        string $articleCode,
    ): LoreArticleRecord {
        if (!$this->canEditWorld($actor, $worldRecord)) {
            throw new LoreDeniedException('Lore article editing is denied');
        }

        return $this->articleRepository->getByCode($worldRecord->getId(), $articleCode);
    }
}
