<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\RuleSpace\Dto\Action\CommitDraftInput;
use Mifrial\Roleplay\RuleSpace\Dto\Action\CreateSpaceInput;
use Mifrial\Roleplay\RuleSpace\Dto\Action\GetRevisionInput;
use Mifrial\Roleplay\RuleSpace\Dto\Action\UpdateSpaceInput;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpacePatch;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceRepository;

/**
 * HTTP-сценарии миров: актор, оператор, JSON.
 */
final class RuleSpaceHttpService
{
    private readonly RuleSpaceDraftPublisher $draftPublisher;

    private const LIST_LIMIT = 500;

    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IRuleSpaces $ruleSpaces Оператор.
     * @param RuleSpaceRepository $ruleSpaceRepository Список sidecar.
     * @param RuleSpaceViewAssembler $viewAssembler JSON.
     * @param RuleSpaceCommitDraftMapper $commitDraftMapper Puts.
     * @param RuleSpaceSlug $ruleSpaceSlug Code.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IRuleSpaces $ruleSpaces,
        private readonly RuleSpaceRepository $ruleSpaceRepository,
        private readonly RuleSpaceViewAssembler $viewAssembler,
        private readonly RuleSpaceCommitDraftMapper $commitDraftMapper,
        private readonly RuleSpaceSlug $ruleSpaceSlug,
    ) {
        $this->draftPublisher = new RuleSpaceDraftPublisher($ruleSpaces);
    }

    /**
     * Живые миры.
     *
     * @return array<int, array<string, mixed>> Space[].
     */
    public function getList(): array
    {
        $views = [];
        foreach ($this->listWorlds($this->actorOrNull()) as $ruleSpaceRecord) {
            $views[] = $this->spaceView($ruleSpaceRecord);
        }

        return $views;
    }

    /**
     * Мир по id.
     *
     * @param int $id Space id.
     *
     * @return array<string, mixed> Space.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function get(int $id): array
    {
        return $this->spaceView($this->requireVisibleWorld($this->loadedWorld($id)));
    }

    /**
     * Мир по коду.
     *
     * @param string $code Ключ URL.
     *
     * @return array<string, mixed> Space.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getByCode(string $code): array
    {
        return $this->spaceView($this->requireVisibleWorld($this->loadedWorldByCode($code)));
    }

    /**
     * Создаёт мир.
     *
     * @param CreateSpaceInput $input JSON.
     *
     * @return array<string, mixed> Space.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function create(CreateSpaceInput $input): array
    {
        $actor = $this->userAccess->requireKey(RuleSpacePermissionKeys::CREATE);
        if ($input->inheritFrom !== null && $input->inheritFrom >= 1) {
            $this->requireVisibleWorld($this->ruleSpaces->get($input->inheritFrom));
        }

        $code = $this->ruleSpaceSlug->resolve($input->code, $input->name);

        return $this->spaceView($this->ruleSpaces->add(
            $code,
            $input->name,
            $actor->getUserId(),
            $input->description,
            $input->inheritFrom,
        ));
    }

    /**
     * Пишет мета.
     *
     * @param UpdateSpaceInput $input JSON.
     *
     * @return array<string, mixed> Space.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function update(UpdateSpaceInput $input): array
    {
        $this->requireEditableWorld($this->loadedWorld($input->id));

        return $this->spaceView($this->ruleSpaces->update(
            $input->id,
            RuleSpacePatch::fromNormalized($this->presentStrings($input)),
        ));
    }

    /**
     * Выключает мир.
     *
     * @param int $id Space id.
     *
     * @return null Успех.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function deactivate(int $id): mixed
    {
        $this->requireEditableWorld($this->loadedWorld($id));
        $this->ruleSpaces->deactivate($id);

        return null;
    }

    /**
     * Лента ревизий.
     *
     * @param int $spaceId Мир.
     *
     * @return array<int, array<string, mixed>> Meta[].
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getRevisions(int $spaceId): array
    {
        $this->requireVisibleWorld($this->loadedWorld($spaceId));
        $views = [];
        foreach ($this->ruleSpaces->getRevisionList($spaceId) as $revisionSummary) {
            $views[] = $this->viewAssembler->assembleRevisionMeta($revisionSummary);
        }

        return $views;
    }

    /**
     * Срез ревизии.
     *
     * @param GetRevisionInput $input JSON.
     *
     * @return array<string, mixed> SpaceRevision.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getRevision(GetRevisionInput $input): array
    {
        $this->requireVisibleWorld($this->loadedWorld($input->spaceId));

        return $this->revisionView($input->spaceId, $input->revision);
    }

    /**
     * Публикует draft.
     *
     * @param CommitDraftInput $input JSON.
     *
     * @return array<string, mixed> SpaceRevision.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws RuleSpaceInvalidException Если номер базы недопустим.
     */
    public function commitDraft(CommitDraftInput $input): array
    {
        $this->requireEditableWorld($this->loadedWorld($input->spaceId));
        $puts = $this->commitDraftMapper->mapPuts($input->rules);
        $removedCodes = $this->commitDraftMapper->mapRemovedCodes($input->removedCodes);
        if ($input->expectedRevision < 0) {
            throw new RuleSpaceInvalidException('Expected revision is invalid');
        }

        $revision = $this->draftPublisher->publish(
            $input->spaceId,
            $puts,
            $removedCodes,
            $input->sections,
            $input->rules,
            $input->expectedRevision,
        );

        return $this->revisionView($input->spaceId, $revision);
    }

    /**
     * JSON Space с latest.
     *
     * @param RuleSpaceRecord $ruleSpaceRecord Мир.
     *
     * @return array<string, mixed> Space.
     */
    private function spaceView(RuleSpaceRecord $ruleSpaceRecord): array
    {
        return $this->viewAssembler->assembleSpace(
            $ruleSpaceRecord,
            $this->ruleSpaces->getRevisionList($ruleSpaceRecord->getId()),
        );
    }

    /**
     * Sidecar. Гостю отсутствующий id — AUTH_REQUIRED.
     *
     * @param int $spaceId Мир.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws RuleSpaceNotFoundException Если мира нет у актора.
     */
    private function loadedWorld(int $spaceId): RuleSpaceRecord
    {
        try {
            return $this->ruleSpaces->get($spaceId);
        } catch (RuleSpaceNotFoundException $exception) {
            $this->userAccess->requireActor();

            throw $exception;
        }
    }

    /**
     * Мир по коду. Гостю отсутствующий код — AUTH_REQUIRED.
     *
     * @param string $code Ключ URL.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws RuleSpaceInvalidException Если code пуст.
     * @throws RuleSpaceNotFoundException Если мира нет у актора.
     */
    private function loadedWorldByCode(string $code): RuleSpaceRecord
    {
        try {
            return $this->ruleSpaces->getByCode($code);
        } catch (RuleSpaceNotFoundException $exception) {
            $this->userAccess->requireActor();

            throw $exception;
        }
    }

    /**
     * Актор или гость.
     *
     * @return RequestActor|null null — гость.
     *
     * @throws ActionException Если отказ не AUTH_REQUIRED.
     */
    private function actorOrNull(): ?RequestActor
    {
        try {
            return $this->userAccess->requireActor();
        } catch (ActionException $exception) {
            if ($exception->getErrorCode() !== 'AUTH_REQUIRED') {
                throw $exception;
            }

            return null;
        }
    }

    /**
     * view_all — все живые. Иначе свои плюс publishedRead=all. Гость — только all.
     *
     * @param RequestActor|null $actor Актор.
     *
     * @return array<int, RuleSpaceRecord> Миры.
     *
     * @throws RuleSpaceInvalidException Если выборка битая.
     */
    private function listWorlds(?RequestActor $actor): array
    {
        if ($actor instanceof RequestActor && $actor->hasKey(RuleSpacePermissionKeys::VIEW_ALL)) {
            return $this->ruleSpaceRepository->getActiveList(self::LIST_LIMIT);
        }

        $published = $this->ruleSpaceRepository->getActiveListByPublishedRead('all', self::LIST_LIMIT);
        if (!$actor instanceof RequestActor) {
            return $published;
        }

        return $this->mergeWorlds(
            $this->ruleSpaceRepository->getActiveListByOwner($actor->getUserId(), self::LIST_LIMIT),
            $published,
        );
    }

    /**
     * Свои миры и публичные без повтора id.
     *
     * @param array<int, RuleSpaceRecord> $owned Свои.
     * @param array<int, RuleSpaceRecord> $published publishedRead=all.
     *
     * @return array<int, RuleSpaceRecord> Список.
     */
    private function mergeWorlds(array $owned, array $published): array
    {
        $worlds = [];
        foreach ([...$owned, ...$published] as $ruleSpaceRecord) {
            $worlds[$ruleSpaceRecord->getId()] = $ruleSpaceRecord;
        }

        return array_values($worlds);
    }

    /**
     * publishedRead=all виден всем, включая гостя. Иначе владелец, view_all или bypass.
     *
     * @param RuleSpaceRecord $ruleSpaceRecord Мир.
     *
     * @return RuleSpaceRecord Тот же мир.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    private function requireVisibleWorld(RuleSpaceRecord $ruleSpaceRecord): RuleSpaceRecord
    {
        if ($ruleSpaceRecord->getPublishedRead() === 'all') {
            return $ruleSpaceRecord;
        }

        $this->userAccess->requireSelfOrKey($ruleSpaceRecord->getOwnerId(), RuleSpacePermissionKeys::VIEW_ALL);

        return $ruleSpaceRecord;
    }

    /**
     * Мир правит владелец, edit_all или bypass.
     *
     * @param RuleSpaceRecord $ruleSpaceRecord Мир.
     *
     * @return RuleSpaceRecord Тот же мир.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    private function requireEditableWorld(RuleSpaceRecord $ruleSpaceRecord): RuleSpaceRecord
    {
        $this->userAccess->requireSelfOrKey($ruleSpaceRecord->getOwnerId(), RuleSpacePermissionKeys::EDIT_ALL);

        return $ruleSpaceRecord;
    }

    /**
     * JSON среза.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return array<string, mixed> SpaceRevision.
     */
    private function revisionView(int $spaceId, int $revision): array
    {
        return $this->viewAssembler->assembleRevision(
            $this->ruleSpaces->get($spaceId),
            $this->ruleSpaces->getRevision($spaceId, $revision),
            $this->ruleSpaces->getCatalog($spaceId, $revision),
        );
    }

    /**
     * Присутствующие строки update.
     *
     * @param UpdateSpaceInput $input JSON.
     *
     * @return array<string, string> Patch.
     *
     * @throws RuleSpaceInvalidException Если JSON null.
     */
    private function presentStrings(UpdateSpaceInput $input): array
    {
        $fields = [];
        if ($input->name->isPresent()) {
            $fields['name'] = $this->presentString($input->name->getValue());
        }

        if ($input->description->isPresent()) {
            $fields['description'] = $this->presentString($input->description->getValue());
        }

        if ($input->publishedRead->isPresent()) {
            $fields['published_read'] = $this->presentString($input->publishedRead->getValue());
        }

        return $fields;
    }

    /**
     * Строка patch.
     *
     * @param string|null $value JSON.
     *
     * @return string Строка.
     *
     * @throws RuleSpaceInvalidException Если null.
     */
    private function presentString(?string $value): string
    {
        if ($value === null) {
            throw new RuleSpaceInvalidException('Rule space patch field is invalid');
        }

        return $value;
    }
}
