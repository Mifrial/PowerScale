<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpecRead;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceSpecRejectedException;

/**
 * Отказ публикации, если spec состава битая.
 */
final class RuleRevisionSpecGuard
{
    private readonly RuleStateReferences $references;

    /**
     * Создаёт проверку.
     *
     * @param IRules $rules Часы.
     *
     * @return void
     */
    public function __construct(
        private readonly IRules $rules,
    ) {
        $this->references = new RuleStateReferences();
    }

    /**
     * Проверяет put и keep состава.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $entries Состав.
     * @param int|null $expectedRevision База keep.
     *
     * @return void
     *
     * @throws RuleSpaceSpecRejectedException Если spec битая.
     * @throws RuleSpaceInvalidException Если keep без базы.
     */
    public function assertEntries(int $spaceId, array $entries, ?int $expectedRevision): void
    {
        $problems = [];
        $composition = [];
        $keepIds = [];
        foreach ($entries as $entry) {
            $body = $entry->getBody();
            $code = $entry->getCode();
            if ($body instanceof RuleVersionBody && is_string($code)) {
                $read = RuleSpecs::read($body->getType(), $body->getSpec());
                $this->append($problems, $code, $body->getName(), $read);
                if ($entry->isActive() && !$read->isBroken()) {
                    $composition[] = [
                        'code' => $code,
                        'type' => $body->getType(),
                        'name' => $body->getName(),
                        'spec' => $body->getSpec(),
                    ];
                }

                continue;
            }

            $versionId = $entry->getVersionId();
            if ($versionId !== null) {
                $keepIds[] = $versionId;
            }
        }

        foreach ($this->kept($spaceId, $keepIds, $expectedRevision) as $record) {
            $read = RuleSpecs::read($record->getType(), $record->getSpecSource());
            $this->append($problems, $record->getCode(), $record->getName(), $read);
            if (!$read->isBroken()) {
                $composition[] = [
                    'code' => $record->getCode(),
                    'type' => $record->getType(),
                    'name' => $record->getName(),
                    'spec' => $record->getSpecSource(),
                ];
            }
        }

        foreach ($this->references->problems($composition) as $problem) {
            $problems[] = $problem;
        }

        if ($problems !== []) {
            throw new RuleSpaceSpecRejectedException($problems);
        }
    }

    /**
     * Проверяет уже разобранные версии.
     *
     * @param array<int, RuleVersionRecord> $records Версии.
     *
     * @return void
     *
     * @throws RuleSpaceSpecRejectedException Если spec битая.
     */
    public function assertRecords(array $records): void
    {
        $problems = [];
        $composition = [];
        foreach ($records as $record) {
            $read = RuleSpecs::read($record->getType(), $record->getSpecSource());
            $this->append($problems, $record->getCode(), $record->getName(), $read);
            if (!$read->isBroken()) {
                $composition[] = [
                    'code' => $record->getCode(),
                    'type' => $record->getType(),
                    'name' => $record->getName(),
                    'spec' => $record->getSpecSource(),
                ];
            }
        }

        foreach ($this->references->problems($composition) as $problem) {
            $problems[] = $problem;
        }

        if ($problems !== []) {
            throw new RuleSpaceSpecRejectedException($problems);
        }
    }

    /**
     * Keep из базы.
     *
     * @param int $spaceId Мир.
     * @param array<int, int> $keepIds Id версий.
     * @param int|null $expectedRevision База.
     *
     * @return array<int, RuleVersionRecord> Версии.
     *
     * @throws RuleSpaceInvalidException Если keep без базы.
     */
    private function kept(int $spaceId, array $keepIds, ?int $expectedRevision): array
    {
        if ($keepIds === []) {
            return [];
        }

        if ($expectedRevision === null || $expectedRevision < 1) {
            throw new RuleSpaceInvalidException('Keep needs a published revision');
        }

        $byId = [];
        foreach ($this->rules->getRevision($spaceId, $expectedRevision)->getItems() as $record) {
            $byId[$record->getVersionId()] = $record;
        }

        $records = [];
        foreach ($keepIds as $versionId) {
            if (isset($byId[$versionId])) {
                $records[] = $byId[$versionId];
            }
        }

        return $records;
    }

    /**
     * Строка problem, если разбор отказал.
     *
     * @param array<int, array{ruleCode: string, ruleName: string, messages: array<int, string>}> $problems Список.
     * @param string $code Код.
     * @param string $name Имя.
     * @param RuleSpecRead $read Итог.
     *
     * @return void
     */
    private function append(array &$problems, string $code, string $name, RuleSpecRead $read): void
    {
        if (!$read->isBroken()) {
            return;
        }

        $key = $read->getShapeKey();
        $problems[] = [
            'ruleCode' => $code,
            'ruleName' => $name,
            'messages' => [$key === '' ? 'Rule spec shape is invalid' : 'spec.' . $key],
        ];
    }
}
