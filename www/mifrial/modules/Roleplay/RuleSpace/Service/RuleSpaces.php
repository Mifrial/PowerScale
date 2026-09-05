<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Один оператор мира: два входа публикации не склеивать в tagged union.

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSummary;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpacePatch;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceRepository;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Один оператор мира: два входа публикации не склеивать в tagged union.

/**
 * Фасад миров: sidecar и часы правила.
 */
final class RuleSpaces implements IRuleSpaces
{
    /**
     * Создаёт фасад.
     *
     * @param IRules $rules Часы правила.
     * @param RuleSpaceRepository $ruleSpaceRepository Sidecar.
     * @param RuleSpaceWorldWriter $worldWriter Insert и commit.
     * @param ISmartTableGateway $smartTableGateway TX inherit.
     *
     * @return void
     */
    public function __construct(
        private readonly IRules $rules,
        private readonly RuleSpaceRepository $ruleSpaceRepository,
        private readonly RuleSpaceWorldWriter $worldWriter,
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Создаёт мир, опционально копируя состав.
     *
     * @param string $code Ключ URL.
     * @param string $name Подпись.
     * @param int $ownerUserId Владелец.
     * @param string $description Текст.
     * @param int|null $inheritFromSpaceId Родитель.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceInvalidException Если вход недопустим.
     * @throws RuleSpaceNotFoundException Если родителя нет.
     */
    public function add(
        string $code,
        string $name,
        int $ownerUserId,
        string $description = '',
        ?int $inheritFromSpaceId = null,
    ): RuleSpaceRecord {
        [$trimmedCode, $trimmedName] = $this->parsedAddNames($code, $name, $ownerUserId, $inheritFromSpaceId);
        $sourceSlice = $this->resolveInheritSlice($inheritFromSpaceId);
        $spaceId = (new RuleSpaceGuard())->run(
            fn (): mixed => $this->persistWorld(
                $trimmedCode,
                $trimmedName,
                $ownerUserId,
                $description,
                $sourceSlice,
                $inheritFromSpaceId,
            ),
        );
        if (!is_int($spaceId)) {
            throw new RuleSpaceInvalidException('Space id is invalid');
        }

        return $this->requireWorld($spaceId);
    }

    /**
     * Мир по id часов.
     *
     * @param int $spaceId Space id.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function get(int $spaceId): RuleSpaceRecord
    {
        return $this->requireWorld($spaceId);
    }

    /**
     * Мир по коду.
     *
     * @param string $code Ключ URL.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceInvalidException Если code пуст.
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function getByCode(string $code): RuleSpaceRecord
    {
        $trimmedCode = trim($code);
        if ($trimmedCode === '') {
            throw new RuleSpaceInvalidException('Rule space code must not be empty');
        }

        $world = (new RuleSpaceGuard())->run(
            fn (): mixed => $this->ruleSpaceRepository->getByCode($trimmedCode),
        );
        if (!$world instanceof RuleSpaceRecord) {
            throw new RuleSpaceInvalidException('Rule space record is invalid');
        }

        return $world;
    }

    /**
     * Обновляет переданные поля мета.
     *
     * @param int $spaceId Мир.
     * @param RuleSpacePatch $patch Присутствующие свойства.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceInvalidException Если patch пуст или name пуст.
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function update(int $spaceId, RuleSpacePatch $patch): RuleSpaceRecord
    {
        $this->requireWorld($spaceId);
        $sidecarFields = $this->sidecarFieldsFromPatch($patch);
        (new RuleSpaceGuard())->run(
            fn (): mixed => $this->writeMeta($spaceId, $sidecarFields),
        );

        return $this->requireWorld($spaceId);
    }

    /**
     * Выключает мир в продукте.
     *
     * @param int $spaceId Мир.
     *
     * @return void
     *
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function deactivate(int $spaceId): void
    {
        $this->requireWorld($spaceId);
        (new RuleSpaceGuard())->run(
            function () use ($spaceId): mixed {
                $this->ruleSpaceRepository->updateBySpaceId($spaceId, ['active' => false]);

                return null;
            },
        );
    }

    /**
     * Срез ревизии мира.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return RuleRevisionSlice Состав.
     *
     * @throws RuleSpaceInvalidException Если номер меньше 1.
     * @throws RuleSpaceNotFoundException Если мира или ревизии нет.
     */
    public function getRevision(int $spaceId, int $revision): RuleRevisionSlice
    {
        $this->requireWorld($spaceId);
        $revisionSlice = (new RuleSpaceGuard())->run(
            fn (): mixed => $this->rules->getRevision($spaceId, $revision),
        );
        if (!$revisionSlice instanceof RuleRevisionSlice) {
            throw new RuleSpaceInvalidException('Revision slice is invalid');
        }

        return $revisionSlice;
    }

    /**
     * Лента ревизий мира.
     *
     * @param int $spaceId Мир.
     *
     * @return array<int, RuleRevisionSummary> Сводки.
     *
     * @throws RuleSpaceNotFoundException Если мира нет.
     * @throws RuleSpaceInvalidException Если запись битая.
     */
    public function getRevisionList(int $spaceId): array
    {
        $this->requireWorld($spaceId);
        $summaries = (new RuleSpaceGuard())->run(
            fn (): mixed => $this->rules->getRevisionList($spaceId),
        );
        if (!is_array($summaries)) {
            throw new RuleSpaceInvalidException('Revision list is invalid');
        }

        return $summaries;
    }

    /**
     * Правило с code в составе.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     * @param string $code Ключ правила.
     *
     * @return RuleVersionRecord Экземпляр.
     *
     * @throws RuleSpaceInvalidException Если code пуст.
     * @throws RuleSpaceNotFoundException Если нет мира или пункта.
     */
    public function findInRevision(int $spaceId, int $revision, string $code): RuleVersionRecord
    {
        $this->requireWorld($spaceId);
        $versionRecord = (new RuleSpaceGuard())->run(
            fn (): mixed => $this->rules->findInRevision($spaceId, $revision, $code),
        );
        if (!$versionRecord instanceof RuleVersionRecord) {
            throw new RuleSpaceInvalidException('Version record is invalid');
        }

        return $versionRecord;
    }

    /**
     * Снимок каталога ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return RuleSpaceCatalog Снимок.
     *
     * @throws RuleSpaceNotFoundException Если мира нет.
     */
    public function getCatalog(int $spaceId, int $revision): RuleSpaceCatalog
    {
        $this->requireWorld($spaceId);

        return $this->worldWriter->getCatalog($spaceId, $revision);
    }

    /**
     * Публикует полный состав мира.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $entries Keep/put.
     * @param RuleSpaceCatalog|null $catalog Явный снимок или шаринг.
     *
     * @return RuleRevisionRecord Ревизия.
     *
     * @throws RuleSpaceInvalidException Если состав недопустим.
     * @throws RuleSpaceNotFoundException Если мира нет.
     */
    public function commit(
        int $spaceId,
        array $entries,
        ?RuleSpaceCatalog $catalog = null,
    ): RuleRevisionRecord {
        $this->requireWorld($spaceId);
        $revisionRecord = (new RuleSpaceGuard())->run(
            function () use ($spaceId, $entries, $catalog): mixed {
                return $this->smartTableGateway->transaction(
                    function () use ($spaceId, $entries, $catalog): RuleRevisionRecord {
                        return $this->worldWriter->publishWithCatalog($spaceId, $entries, $catalog);
                    },
                );
            },
        );
        if (!$revisionRecord instanceof RuleRevisionRecord) {
            throw new RuleSpaceInvalidException('Revision record is invalid');
        }

        return $revisionRecord;
    }

    /**
     * Публикует выбранные put и tombstone относительно базы.
     *
     * @param int $spaceId Мир.
     * @param RuleSpaceSelection $selection База и выбор.
     * @param RuleSpaceCatalog|null $catalog Явный снимок или шаринг.
     *
     * @return RuleRevisionRecord Ревизия.
     *
     * @throws RuleSpaceInvalidException Если выбор или состав недопустимы.
     * @throws RuleSpaceNotFoundException Если мира или базы нет.
     */
    public function commitSelected(
        int $spaceId,
        RuleSpaceSelection $selection,
        ?RuleSpaceCatalog $catalog = null,
    ): RuleRevisionRecord {
        $this->requireWorld($spaceId);
        $revisionSlice = $this->getRevision($spaceId, $selection->getBaseRevision());

        return $this->commit(
            $spaceId,
            (new RuleSpaceCommitAssembler())->assemble($revisionSlice, $selection),
            $catalog,
        );
    }

    /**
     * Sidecar мира или NOT_FOUND; ST → INVALID.
     *
     * @param int $spaceId Часы.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     * @throws RuleSpaceInvalidException Если ST или Record.
     */
    private function requireWorld(int $spaceId): RuleSpaceRecord
    {
        $world = (new RuleSpaceGuard())->run(
            fn (): mixed => $this->ruleSpaceRepository->getBySpaceId($spaceId),
        );
        if (!$world instanceof RuleSpaceRecord) {
            throw new RuleSpaceInvalidException('Rule space record is invalid');
        }

        return $world;
    }

    /**
     * Колонки sidecar из Patch.
     *
     * @param RuleSpacePatch $patch Вход.
     *
     * @return array<string, string> name и/или description.
     *
     * @throws RuleSpaceInvalidException Если пусто или name пуст.
     */
    private function sidecarFieldsFromPatch(RuleSpacePatch $patch): array
    {
        $patchFields = $patch->fields();
        if ($patchFields === []) {
            throw new RuleSpaceInvalidException('Rule space patch is empty');
        }

        $sidecarFields = [];
        if (array_key_exists('name', $patchFields)) {
            $trimmedName = trim((string) $patchFields['name']);
            if ($trimmedName === '') {
                throw new RuleSpaceInvalidException('Rule space name must not be empty');
            }

            $sidecarFields['name'] = $trimmedName;
        }

        if (array_key_exists('description', $patchFields)) {
            $sidecarFields['description'] = (string) $patchFields['description'];
        }

        return $sidecarFields;
    }

    /**
     * Sidecar и опционально title часов.
     *
     * @param int $spaceId Мир.
     * @param array<string, string> $sidecarFields Колонки.
     *
     * @return null Пусто.
     *
     * @throws RuleSpaceInvalidException Если unique или поле.
     * @throws RuleSpaceNotFoundException Если часов нет.
     */
    private function writeMeta(int $spaceId, array $sidecarFields): mixed
    {
        $spaceName = $sidecarFields['name'] ?? null;
        if (is_string($spaceName)) {
            $this->smartTableGateway->transaction(
                function () use ($spaceId, $sidecarFields, $spaceName): int {
                    $this->rules->updateSpace($spaceId, $spaceName);
                    $this->ruleSpaceRepository->updateBySpaceId($spaceId, $sidecarFields);

                    return $spaceId;
                },
            );

            return null;
        }

        $this->ruleSpaceRepository->updateBySpaceId($spaceId, $sidecarFields);

        return null;
    }

    /**
     * Trim и проверки входа add.
     *
     * @param string $code Ключ.
     * @param string $name Подпись.
     * @param int $ownerUserId Владелец.
     * @param int|null $inheritFromSpaceId Родитель.
     *
     * @return array{0: string, 1: string} Code и name.
     *
     * @throws RuleSpaceInvalidException Если пусто, owner или inherit id.
     */
    private function parsedAddNames(string $code, string $name, int $ownerUserId, ?int $inheritFromSpaceId): array
    {
        $trimmedCode = trim($code);
        $trimmedName = trim($name);
        if ($trimmedCode === '' || $trimmedName === '') {
            throw new RuleSpaceInvalidException('Rule space code and name must not be empty');
        }

        if ($ownerUserId < 1) {
            throw new RuleSpaceInvalidException('Owner user id is invalid');
        }

        if ($inheritFromSpaceId !== null && $inheritFromSpaceId < 1) {
            throw new RuleSpaceInvalidException('Inherit space id is invalid');
        }

        return [$trimmedCode, $trimmedName];
    }

    /**
     * Срез родителя или null, если ревизий нет.
     *
     * @param int|null $inheritFromSpaceId Родитель.
     *
     * @return RuleRevisionSlice|null Состав.
     *
     * @throws RuleSpaceNotFoundException Если мира нет.
     * @throws RuleSpaceInvalidException Если запись битая.
     */
    private function resolveInheritSlice(?int $inheritFromSpaceId): ?RuleRevisionSlice
    {
        if ($inheritFromSpaceId === null) {
            return null;
        }

        $this->requireWorld($inheritFromSpaceId);
        $latest = (new RuleSpaceGuard())->run(
            fn (): mixed => $this->rules->findLatestRevision($inheritFromSpaceId),
        );
        if ($latest === null) {
            return null;
        }

        if (!$latest instanceof RuleRevisionRecord) {
            throw new RuleSpaceInvalidException('Revision record is invalid');
        }

        return $this->getRevision($inheritFromSpaceId, $latest->getRevision());
    }

    /**
     * TX: часы и sidecar.
     *
     * @param string $code Ключ.
     * @param string $name Подпись.
     * @param int $ownerUserId Владелец.
     * @param string $description Текст.
     * @param RuleRevisionSlice|null $sourceSlice Состав.
     * @param int|null $inheritFromSpaceId Родитель.
     *
     * @return int Space id.
     *
     * @throws RuleSpaceInvalidException Если id не int.
     */
    private function persistWorld(
        string $code,
        string $name,
        int $ownerUserId,
        string $description,
        ?RuleRevisionSlice $sourceSlice,
        ?int $inheritFromSpaceId,
    ): int {
        $spaceId = $this->smartTableGateway->transaction(
            function () use ($code, $name, $ownerUserId, $description, $sourceSlice, $inheritFromSpaceId): int {
                return $this->worldWriter->insertWorld(
                    $code,
                    $name,
                    $ownerUserId,
                    $description,
                    $sourceSlice,
                    $inheritFromSpaceId,
                );
            },
        );
        if (!is_int($spaceId)) {
            throw new RuleSpaceInvalidException('Space id is invalid');
        }

        return $spaceId;
    }
}
