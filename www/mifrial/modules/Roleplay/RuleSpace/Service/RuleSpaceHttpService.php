<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Один HTTP-сценарий мира: девять ruleSpace.*, не второй фасад.

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\RuleSpace\Dto\Action\CommitDraftInput;
use Mifrial\Roleplay\RuleSpace\Dto\Action\CreateSpaceInput;
use Mifrial\Roleplay\RuleSpace\Dto\Action\GetRevisionInput;
use Mifrial\Roleplay\RuleSpace\Dto\Action\UpdateSpaceInput;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpacePatch;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceRepository;

/**
 * HTTP-сценарии миров: актор, оператор, JSON.
 */
final class RuleSpaceHttpService
{
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
    }

    /**
     * Живые миры.
     *
     * @return array<int, array<string, mixed>> Space[].
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getList(): array
    {
        $actor = $this->userAccess->requireActor();
        $records = $actor->hasKey(RuleSpacePermissionKeys::VIEW_ALL)
            ? $this->ruleSpaceRepository->getActiveList(self::LIST_LIMIT)
            : $this->ruleSpaceRepository->getActiveListByOwner($actor->getUserId(), self::LIST_LIMIT);
        $views = [];
        foreach ($records as $ruleSpaceRecord) {
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
        $this->userAccess->requireActor();

        return $this->spaceView($this->requireVisibleWorld($this->ruleSpaces->getByCode($code)));
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
     */
    public function commitDraft(CommitDraftInput $input): array
    {
        $this->requireEditableWorld($this->loadedWorld($input->spaceId));
        $puts = $this->commitDraftMapper->mapPuts($input->rules);
        $removedCodes = $this->commitDraftMapper->mapRemovedCodes($input->removedCodes);
        $catalog = $this->draftCatalog($input->spaceId, $input->sections, $input->rules, $puts);
        $revision = $this->publishDraft($input->spaceId, $puts, $removedCodes, $catalog);

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
     * Sidecar после актора.
     *
     * @param int $spaceId Мир.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    private function loadedWorld(int $spaceId): RuleSpaceRecord
    {
        $this->userAccess->requireActor();

        return $this->ruleSpaces->get($spaceId);
    }

    /**
     * Мир виден владельцу, view_all или bypass.
     *
     * @param RuleSpaceRecord $ruleSpaceRecord Мир.
     *
     * @return RuleSpaceRecord Тот же мир.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    private function requireVisibleWorld(RuleSpaceRecord $ruleSpaceRecord): RuleSpaceRecord
    {
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
     * Первая ревизия — полный put; иначе выбор поверх latest.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param array<int, string> $removedCodes Tombstone.
     * @param RuleSpaceCatalog|null $catalog Снимок.
     *
     * @return int Номер новой ревизии.
     *
     * @throws RuleSpaceInvalidException Если состав недопустим.
     */
    private function publishDraft(
        int $spaceId,
        array $puts,
        array $removedCodes,
        ?RuleSpaceCatalog $catalog,
    ): int {
        $summaries = $this->ruleSpaces->getRevisionList($spaceId);
        $latest = $summaries[0] ?? null;
        if ($latest === null) {
            return $this->publishFirstDraft($spaceId, $puts, $removedCodes, $catalog);
        }

        if ($puts === [] && $removedCodes === [] && $catalog instanceof RuleSpaceCatalog) {
            $slice = $this->ruleSpaces->getRevision($spaceId, $latest->getRevision());

            return $this->ruleSpaces->commit(
                $spaceId,
                $this->keepEntries($slice),
                $catalog,
            )->getRevision();
        }

        return $this->ruleSpaces->commitSelected(
            $spaceId,
            RuleSpaceSelection::fromParts($latest->getRevision(), $puts, $removedCodes),
            $catalog,
        )->getRevision();
    }

    /**
     * Первая ревизия: каталог-only недопустим.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param array<int, string> $removedCodes Tombstone.
     * @param RuleSpaceCatalog|null $catalog Снимок.
     *
     * @return int Номер.
     *
     * @throws RuleSpaceInvalidException Если removed или пустой состав.
     */
    private function publishFirstDraft(
        int $spaceId,
        array $puts,
        array $removedCodes,
        ?RuleSpaceCatalog $catalog,
    ): int {
        if ($removedCodes !== []) {
            throw new RuleSpaceInvalidException('Removed codes need a published revision');
        }

        if ($puts === [] && $catalog instanceof RuleSpaceCatalog) {
            throw new RuleSpaceInvalidException('Catalog-only needs a published revision');
        }

        return $this->ruleSpaces->commit($spaceId, $puts, $catalog)->getRevision();
    }

    /**
     * Снимок из JSON sections или null.
     *
     * @param int $spaceId Мир.
     * @param OptionalArray $sections Ключ JSON.
     * @param array<int, mixed> $putRows JSON rules.
     * @param array<int, RuleCommitEntry> $puts Put.
     *
     * @return RuleSpaceCatalog|null Снимок.
     *
     * @throws RuleSpaceInvalidException Если дерево.
     */
    private function draftCatalog(
        int $spaceId,
        OptionalArray $sections,
        array $putRows,
        array $puts,
    ): ?RuleSpaceCatalog {
        if (!$sections->isPresent()) {
            return null;
        }

        $jsonMapper = new RuleSpaceCatalogJsonMapper();
        $mappedSections = $jsonMapper->mapSections($sections->getValue());
        $summaries = $this->ruleSpaces->getRevisionList($spaceId);
        $latest = $summaries[0] ?? null;
        $latestCatalog = RuleSpaceCatalog::empty();
        $omitKeepCodes = [];
        if ($latest !== null) {
            $slice = $this->ruleSpaces->getRevision($spaceId, $latest->getRevision());
            $latestCatalog = $this->ruleSpaces->getCatalog($spaceId, $latest->getRevision());
            $omitKeepCodes = $this->omitKeepCodes($slice, $puts);
        }

        return (new RuleSpaceCatalogDraftComposer())->compose(
            $mappedSections,
            $puts,
            $putRows,
            $latestCatalog,
            $omitKeepCodes,
        );
    }

    /**
     * Коды состава кроме put.
     *
     * @param RuleRevisionSlice $revisionSlice Latest.
     * @param array<int, RuleCommitEntry> $puts Put.
     *
     * @return array<string, true> Коды.
     */
    private function omitKeepCodes(RuleRevisionSlice $revisionSlice, array $puts): array
    {
        $putCodes = [];
        foreach ($puts as $putEntry) {
            $code = $putEntry->getCode();
            if ($code !== null) {
                $putCodes[$code] = true;
            }
        }

        $omitKeepCodes = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $code = $versionRecord->getCode();
            if (!isset($putCodes[$code])) {
                $omitKeepCodes[$code] = true;
            }
        }

        return $omitKeepCodes;
    }

    /**
     * Keep всего среза.
     *
     * @param RuleRevisionSlice $revisionSlice Состав.
     *
     * @return array<int, RuleCommitEntry> Keep.
     */
    private function keepEntries(RuleRevisionSlice $revisionSlice): array
    {
        $entries = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $entries[] = RuleCommitEntry::keep($versionRecord->getVersionId());
        }

        return $entries;
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
