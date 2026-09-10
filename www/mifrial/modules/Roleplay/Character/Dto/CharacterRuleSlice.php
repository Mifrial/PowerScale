<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Индекс среза ревизии: live по code и tombstone.
 */
final class CharacterRuleSlice
{
    /**
     * @var array<string, CharacterResolvedRule>
     */
    private readonly array $liveByCode;

    /**
     * Создаёт срез.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     * @param string $spaceCode Ключ мира.
     * @param array<int, CharacterResolvedRule> $liveRules Live в порядке состава.
     * @param array<string, true> $tombstoneCodes Снятые code.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если дубль live code или code и live, и tombstone.
     */
    public function __construct(
        private readonly int $spaceId,
        private readonly int $revision,
        private readonly string $spaceCode,
        private readonly array $liveRules,
        private readonly array $tombstoneCodes,
    ) {
        $this->liveByCode = $this->indexLiveRules($liveRules, $tombstoneCodes);
    }

    /**
     * Id мира.
     *
     * @return int Space id.
     */
    public function getSpaceId(): int
    {
        return $this->spaceId;
    }

    /**
     * Номер ревизии.
     *
     * @return int Номер.
     */
    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Код мира.
     *
     * @return string Ключ URL.
     */
    public function getSpaceCode(): string
    {
        return $this->spaceCode;
    }

    /**
     * Живое правило по code.
     *
     * @param string $code Ключ правила.
     *
     * @return CharacterResolvedRule|null Пункт или null.
     */
    public function findLive(string $code): ?CharacterResolvedRule
    {
        return $this->liveByCode[$code] ?? null;
    }

    /**
     * Code снят tombstone.
     *
     * @param string $code Ключ правила.
     *
     * @return bool true, если tombstone.
     */
    public function hasTombstone(string $code): bool
    {
        return isset($this->tombstoneCodes[$code]);
    }

    /**
     * Live в порядке состава.
     *
     * @return array<int, CharacterResolvedRule> Список.
     */
    public function getLiveRules(): array
    {
        return $this->liveRules;
    }

    /**
     * Карта live; дубль code недопустим.
     *
     * @param array<int, CharacterResolvedRule> $liveRules Live.
     * @param array<string, true> $tombstoneCodes Tombstone.
     *
     * @return array<string, CharacterResolvedRule> По code.
     *
     * @throws CharacterInvalidException Если дубль или пересечение с tombstone.
     */
    private function indexLiveRules(array $liveRules, array $tombstoneCodes): array
    {
        $liveByCode = [];
        foreach ($liveRules as $liveRule) {
            $code = $liveRule->getCode();
            if (isset($liveByCode[$code]) || isset($tombstoneCodes[$code])) {
                throw new CharacterInvalidException('Rule slice has a duplicate code');
            }

            $liveByCode[$code] = $liveRule;
        }

        return $liveByCode;
    }
}
