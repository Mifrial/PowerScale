<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Service;

use JsonException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Versioning\Space\Dto\RecordValue;
use Mifrial\Versioning\Space\Dto\RevisionRecord;
use Mifrial\Versioning\Space\Dto\RevisionSlice;
use Mifrial\Versioning\Space\Dto\VersionRecord;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * JSON готового среза без PHP serialize.
 */
final class RevisionSliceCodec
{
    /**
     * Кодирует срез в JSON.
     *
     * @param RevisionSlice $revisionSlice Срез.
     *
     * @return string Payload.
     *
     * @throws SpaceInvalidException Если JSON не собрался.
     */
    public function encode(RevisionSlice $revisionSlice): string
    {
        $revisionRecord = $revisionSlice->getRevision();
        $items = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $items[] = $this->encodeItem($versionRecord);
        }

        try {
            return json_encode(
                [
                    'revision' => [
                        'id' => $revisionRecord->getId(),
                        'spaceId' => $revisionRecord->getSpaceId(),
                        'revision' => $revisionRecord->getRevision(),
                        'publishedAt' => $revisionRecord->getPublishedAt()->toUnix(),
                    ],
                    'items' => $items,
                ],
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new SpaceInvalidException('Revision slice JSON is invalid', $exception);
        }
    }

    /**
     * Собирает срез из JSON.
     *
     * @param string $payload Байты кэша.
     *
     * @return RevisionSlice Срез.
     *
     * @throws SpaceInvalidException Если JSON битый.
     */
    public function decode(string $payload): RevisionSlice
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SpaceInvalidException('Revision slice JSON is invalid', $exception);
        }

        if (!is_array($decoded) || !isset($decoded['revision'], $decoded['items']) || !is_array($decoded['items'])) {
            throw new SpaceInvalidException('Revision slice JSON is invalid');
        }

        return new RevisionSlice(
            $this->decodeRevision($decoded['revision']),
            $this->decodeItems($decoded['items']),
        );
    }

    /**
     * Кодирует экземпляр.
     *
     * @param VersionRecord $versionRecord Экземпляр.
     *
     * @return array<string, mixed> JSON-ряд.
     */
    private function encodeItem(VersionRecord $versionRecord): array
    {
        return [
            'versionId' => $versionRecord->getVersionId(),
            'entityId' => $versionRecord->getEntityId(),
            'active' => $versionRecord->isActive(),
            'fields' => $versionRecord->getFields(),
            'createdAt' => $versionRecord->getCreatedAt()->toUnix(),
        ];
    }

    /**
     * Ревизия из JSON.
     *
     * @param mixed $revisionValue Узел revision.
     *
     * @return RevisionRecord Ревизия.
     *
     * @throws SpaceInvalidException Если узел неверен.
     */
    private function decodeRevision(mixed $revisionValue): RevisionRecord
    {
        if (!is_array($revisionValue)) {
            throw new SpaceInvalidException('Revision slice JSON is invalid');
        }

        return new RevisionRecord(
            RecordValue::requireInt($revisionValue, 'id'),
            RecordValue::requireInt($revisionValue, 'spaceId'),
            RecordValue::requireInt($revisionValue, 'revision'),
            DateTime::fromUnix(RecordValue::requireInt($revisionValue, 'publishedAt')),
        );
    }

    /**
     * Пункты из JSON.
     *
     * @param array<int|string, mixed> $itemValues Узлы items.
     *
     * @return array<int, VersionRecord> Экземпляры.
     *
     * @throws SpaceInvalidException Если пункт неверен.
     */
    private function decodeItems(array $itemValues): array
    {
        $items = [];
        foreach ($itemValues as $itemValue) {
            $items[] = $this->decodeItem($itemValue);
        }

        return $items;
    }

    /**
     * Один пункт из JSON.
     *
     * @param mixed $itemValue Узел.
     *
     * @return VersionRecord Экземпляр.
     *
     * @throws SpaceInvalidException Если пункт неверен.
     */
    private function decodeItem(mixed $itemValue): VersionRecord
    {
        if (!is_array($itemValue)) {
            throw new SpaceInvalidException('Revision slice JSON is invalid');
        }

        $fields = $itemValue['fields'] ?? null;
        if (!is_array($fields)) {
            throw new SpaceInvalidException('Revision slice JSON is invalid');
        }

        return new VersionRecord(
            RecordValue::requireInt($itemValue, 'versionId'),
            RecordValue::requireInt($itemValue, 'entityId'),
            RecordValue::requireBool($itemValue, 'active'),
            $fields,
            DateTime::fromUnix(RecordValue::requireInt($itemValue, 'createdAt')),
        );
    }
}
