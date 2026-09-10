<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

/**
 * Загрузчик среза (spaceId, revision) через IRuleSpaces.
 */
final class CharacterRuleSlices implements ICharacterRuleSlices
{
    /**
     * Создаёт загрузчик.
     *
     * @param IRuleSpaces $ruleSpaces Миры.
     * @param IKeywords $keywords Признаки.
     *
     * @return void
     */
    public function __construct(
        private readonly IRuleSpaces $ruleSpaces,
        private readonly IKeywords $keywords,
    ) {
    }

    /**
     * Загружает immutable срез (spaceId, revision).
     *
     * @param int $spaceId Id часов / мира.
     * @param int $revision Номер ревизии.
     *
     * @return CharacterRuleSlice Состав live и tombstone.
     *
     * @throws CharacterInvalidException Если мир выключен, номер битый или состав/признаки.
     * @throws CharacterNotFoundException Если нет sidecar или ревизии.
     */
    public function get(int $spaceId, int $revision): CharacterRuleSlice
    {
        try {
            return $this->load($spaceId, $revision);
        } catch (RuleSpaceNotFoundException $exception) {
            throw new CharacterNotFoundException('Character was not found', $exception);
        } catch (RuleSpaceInvalidException | KeywordNotFoundException | KeywordInvalidException $exception) {
            throw new CharacterInvalidException('Character values are invalid', $exception);
        }
    }

    /**
     * Мир, ревизия, индекс и коды признаков.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return CharacterRuleSlice Срез.
     *
     * @throws CharacterInvalidException Если мир выключен или состав битый.
     * @throws RuleSpaceNotFoundException Если нет sidecar или ревизии.
     * @throws RuleSpaceInvalidException Если номер недопустим.
     * @throws KeywordNotFoundException Если id признака нет.
     * @throws KeywordInvalidException Если справочник битый.
     */
    private function load(int $spaceId, int $revision): CharacterRuleSlice
    {
        $world = $this->ruleSpaces->get($spaceId);
        if (!$world->isActive()) {
            throw new CharacterInvalidException('Rule space is inactive');
        }

        return $this->assemble($world, $this->ruleSpaces->getRevision($spaceId, $revision));
    }

    /**
     * Собирает DTO из состава.
     *
     * @param RuleSpaceRecord $world Мир.
     * @param RuleRevisionSlice $revisionSlice Состав.
     *
     * @return CharacterRuleSlice Срез.
     *
     * @throws CharacterInvalidException Если дубль code.
     * @throws KeywordNotFoundException Если id признака нет.
     * @throws KeywordInvalidException Если справочник битый.
     */
    private function assemble(RuleSpaceRecord $world, RuleRevisionSlice $revisionSlice): CharacterRuleSlice
    {
        $indexed = $this->indexItems($revisionSlice->getItems());
        $codeByKeywordId = $this->resolveKeywordCodesById($indexed['live']);
        $liveRules = [];
        foreach ($indexed['live'] as $ruleVersionRecord) {
            $liveRules[] = new CharacterResolvedRule(
                $ruleVersionRecord,
                $this->collectCodesForRule($ruleVersionRecord, $codeByKeywordId),
            );
        }

        return new CharacterRuleSlice(
            $world->getId(),
            $revisionSlice->getRevision()->getRevision(),
            $world->getCode(),
            $liveRules,
            $indexed['tombstones'],
        );
    }

    /**
     * Live в порядке состава и множество tombstone.
     *
     * @param array<int, RuleVersionRecord> $items Пункты среза.
     *
     * @return array{live: array<int, RuleVersionRecord>, tombstones: array<string, true>} Индекс.
     *
     * @throws CharacterInvalidException Если дубль code.
     */
    private function indexItems(array $items): array
    {
        $live = [];
        $tombstones = [];
        foreach ($items as $item) {
            $code = $item->getCode();
            if (isset($live[$code]) || isset($tombstones[$code])) {
                throw new CharacterInvalidException('Rule slice has a duplicate code');
            }

            if ($item->isActive()) {
                $live[$code] = $item;
            } else {
                $tombstones[$code] = true;
            }
        }

        return [
            'live' => array_values($live),
            'tombstones' => $tombstones,
        ];
    }

    /**
     * Id признака → code. Только live.
     *
     * @param array<int, RuleVersionRecord> $liveRules Live.
     *
     * @return array<int, string> Карта.
     *
     * @throws KeywordNotFoundException Если id нет.
     * @throws KeywordInvalidException Если справочник битый.
     */
    private function resolveKeywordCodesById(array $liveRules): array
    {
        $codeByKeywordId = [];
        foreach ($this->collectUniqueKeywordIds($liveRules) as $keywordId) {
            $codeByKeywordId[$keywordId] = $this->keywords->get($keywordId)->getCode();
        }

        return $codeByKeywordId;
    }

    /**
     * Уникальные id признаков в порядке первого появления.
     *
     * @param array<int, RuleVersionRecord> $liveRules Live.
     *
     * @return array<int, int> Id.
     */
    private function collectUniqueKeywordIds(array $liveRules): array
    {
        $seen = [];
        $keywordIds = [];
        foreach ($liveRules as $liveRule) {
            foreach ($liveRule->getKeywordIds() as $keywordId) {
                if (isset($seen[$keywordId])) {
                    continue;
                }

                $seen[$keywordId] = true;
                $keywordIds[] = $keywordId;
            }
        }

        return $keywordIds;
    }

    /**
     * Коды признаков пункта; дубль id схлопывается.
     *
     * @param RuleVersionRecord $ruleVersionRecord Пункт.
     * @param array<int, string> $codeByKeywordId Карта.
     *
     * @return array<int, string> Коды.
     */
    private function collectCodesForRule(RuleVersionRecord $ruleVersionRecord, array $codeByKeywordId): array
    {
        $seen = [];
        $keywordCodes = [];
        foreach ($ruleVersionRecord->getKeywordIds() as $keywordId) {
            if (isset($seen[$keywordId])) {
                continue;
            }

            $seen[$keywordId] = true;
            $keywordCodes[] = $codeByKeywordId[$keywordId];
        }

        return $keywordCodes;
    }
}
